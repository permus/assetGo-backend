<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Requests\Asset\BulkImportAssetRequest;
use App\Http\Requests\Asset\TransferAssetRequest;
use App\Http\Requests\Asset\MaintenanceScheduleRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetTag;
use App\Models\AssetImage;
use App\Models\AssetTransfer;
use App\Models\AssetActivity;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Location;
use App\Models\LocationType;
use App\Services\QRCodeService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    protected $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    // List assets (grid/list view)
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = Asset::with(['category', 'assetType', 'assetStatus', 'department', 'tags', 'images', 'location', 'user', 'company'])->where('company_id', $companyId);

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('serial_number', $search)
                  ->orWhere('description', 'like', "%$search%")
                  ->orWhereHas('tags', function ($tagQ) use ($search) {
                      $tagQ->where('name', 'like', "%$search%") ;
                  });
            });
        }

        // Filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('id', $request->tag_id);
            });
        }
        if ($request->filled('min_value')) {
            $query->where('purchase_price', '>=', $request->min_value);
        }
        if ($request->filled('max_value')) {
            $query->where('purchase_price', '<=', $request->max_value);
        }
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }
        // Archived filter
        if ($request->filled('archived') && $request->boolean('archived')) {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $assets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'assets' => $assets->items(),
                'pagination' => [
                    'current_page' => $assets->currentPage(),
                    'last_page' => $assets->lastPage(),
                    'per_page' => $assets->perPage(),
                    'total' => $assets->total(),
                    'from' => $assets->firstItem(),
                    'to' => $assets->lastItem(),
                ],
                'filters' => $request->all(),
            ]
        ]);
    }

    // Show asset detail
    public function show(Asset $asset)
    {
        // Generate QR code if it does not exist
        if (!$asset->qr_code_path) {
            $qrPath = $this->qrCodeService->generateAssetQRCode($asset);
            if ($qrPath) {
                $asset->qr_code_path = $qrPath;
                $asset->save();
            }
        }

        $asset->load(['category', 'assetType', 'assetStatus', 'department', 'tags', 'images', 'location', 'user', 'company', 'maintenanceSchedules', 'activities', 'parent', 'children']);

        $assetArray = $asset->toArray();
        $assetArray['qr_code_url'] = $asset->qr_code_path ? \Storage::disk('public')->url($asset->qr_code_path) : null;
        $assetArray['quick_chart_qr_url'] = $asset->quick_chart_qr_url;
        $assetArray['barcode_url'] = $this->buildBarcodeUrl($asset->asset_id, 'code128', 300, 100);

        return response()->json([
            'success' => true,
            'data' => [
                'asset' => $assetArray,
            ]
        ]);
    }

    // Create asset
    public function store(StoreAssetRequest $request)
    {
        \DB::beginTransaction();
        try {
            // Generate unique asset ID
            $assetId = 'AST-' . strtoupper(uniqid());

            // Remove company_id from validated data if present
            $data = $request->validated();

            // Create asset with user's company_id
            // Prepare asset data
            $assetData = $data;
            $assetData['company_id'] = $request->user()->company_id;
            $assetData['user_id'] = $request->user()->id;
            $assetData['asset_id'] = $assetId;
            $assetData['status'] = $data['status'] ?? null;
            $asset = Asset::create($assetData);

            // Handle tags
            if ($request->filled('tags')) {
                $tagIds = [];
                foreach ($request->tags as $tagName) {
                    // Find existing tag or create new one
                    $tag = \App\Models\AssetTag::firstOrCreate(['name' => $tagName]);
                    $tagIds[] = $tag->id;
                }
                $asset->tags()->sync($tagIds);
            }

            // Handle images (base64)
            if ($request->filled('images')) {
                foreach ($request->images as $base64Image) {
                    if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                        $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
                        $type = strtolower($type[1]); // jpg, png, gif

                        $base64Image = str_replace(' ', '+', $base64Image);
                        $imageData = base64_decode($base64Image);

                        $fileName = uniqid('asset_') . '.' . $type;
                        $filePath = 'assets/images/' . $fileName;
                        \Storage::disk('public')->put($filePath, $imageData);

                        $asset->images()->create(['image_path' => $filePath]);
                    }
                }
            }

            // Generate QR code (using QRCodeService)
            $qrPath = $this->qrCodeService->generateAssetQRCode($asset);
            if ($qrPath) {
                $asset->qr_code_path = $qrPath;
                $asset->save();
            }

            // Log activity
            $asset->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'created',
                'after' => $asset->toArray(),
                'comment' => 'Asset created',
            ]);

            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Asset created successfully',
                'data' => $asset->load(['category', 'assetType', 'assetStatus', 'department', 'tags', 'images', 'location', 'user', 'company'])
            ], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create asset',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update asset
    public function update(UpdateAssetRequest $request, Asset $asset)
    {
        \DB::beginTransaction();
        try {
            // Prevent serial number change if asset has transfers
            if ($request->has('serial_number') && $request->serial_number !== $asset->serial_number) {
                if ($asset->transfers()->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot change serial number after asset has been transferred.'
                    ], 400);
                }
            }

            $before = $asset->toArray();
            $asset->update($request->validated());

            // Handle tags
            if ($request->filled('tags')) {
                $tagIds = [];
                foreach ($request->tags as $tagName) {
                    // Find existing tag or create new one
                    $tag = \App\Models\AssetTag::firstOrCreate(['name' => $tagName]);
                    $tagIds[] = $tag->id;
                }
                $asset->tags()->sync($tagIds);
            }

            // Handle image removal
            if ($request->filled('remove_image_ids')) {
                $removeImageIds = $request->remove_image_ids;
                $asset->images()->whereIn('id', $removeImageIds)->delete();
            }

            // Handle images (base64)
            if ($request->filled('images')) {
                foreach ($request->images as $base64Image) {
                    if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                        $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
                        $type = strtolower($type[1]); // jpg, png, gif

                        $base64Image = str_replace(' ', '+', $base64Image);
                        $imageData = base64_decode($base64Image);

                        $fileName = uniqid('asset_') . '.' . $type;
                        $filePath = 'assets/images/' . $fileName;
                        \Storage::disk('public')->put($filePath, $imageData);

                        $asset->images()->create(['image_path' => $filePath]);
                    }
                }
            }

            // Log activity
            $asset->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'updated',
                'before' => $before,
                'after' => $asset->toArray(),
                'comment' => 'Asset updated',
            ]);

            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Asset updated successfully',
                'data' => $asset->load(['category', 'assetType', 'assetStatus', 'department', 'tags', 'images', 'location', 'user', 'company'])
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update asset',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete/archive asset
    public function destroy(Request $request, Asset $asset)
    {
        \DB::beginTransaction();
        try {
            // Check for active transfers or maintenance
            if ($asset->transfers()->where('status', 'pending')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete asset with active transfers.'
                ], 400);
            }
            if ($asset->maintenanceSchedules()->where('status', 'active')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete asset with active maintenance.'
                ], 400);
            }

            $before = $asset->toArray();
            // Permanent delete (force delete)
            if ($request->has('force') && $request->boolean('force')) {
                if ($request->filled('deletion_reason')) {
                    $asset->deletion_reason = $request->deletion_reason;
                    $asset->save();
                }
                if ($asset->qr_code_path) {
                    $this->qrCodeService->deleteQRCode($asset->qr_code_path);
                }
                $asset->forceDelete();
                $asset->activities()->create([
                    'user_id' => $request->user()->id,
                    'action' => 'deleted',
                    'before' => $before,
                    'after' => null,
                    'comment' => 'Asset permanently deleted',
                ]);
                \DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Asset permanently deleted successfully',
                ]);
            }
            // Set archive_reason if provided and set status to archived
            if ($request->filled('archive_reason')) {
                $asset->archive_reason = $request->archive_reason;
            }
            $asset->status = 'archived';
            $asset->save();
            $asset->delete(); // Soft delete

            // Log activity
            $asset->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'deleted',
                'before' => $before,
                'after' => null,
                'comment' => 'Asset deleted (archived)',
            ]);

            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Asset deleted (archived) successfully',
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete asset',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Archive (soft delete) an asset
     */
    public function archive(Request $request, Asset $asset)
    {
        \DB::beginTransaction();
        try {
            // Check for active transfers or maintenance
            if ($asset->transfers()->where('status', 'pending')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot archive asset with active transfers.'
                ], 400);
            }
            if ($asset->maintenanceSchedules()->where('status', 'active')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot archive asset with active maintenance.'
                ], 400);
            }

            $before = $asset->toArray();
            // Set archive_reason if provided and set status to archived
            if ($request->filled('archive_reason')) {
                $asset->archive_reason = $request->archive_reason;
            }
            $asset->status = 'archived';
            $asset->save();
            $asset->delete(); // Soft delete

            // Log activity
            $asset->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'archived',
                'before' => $before,
                'after' => null,
                'comment' => 'Asset archived',
            ]);

            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Asset archived successfully',
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive asset',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Duplicate asset
    public function duplicate(Request $request, Asset $asset)
    {
        \DB::beginTransaction();
        try {
            $data = $asset->toArray();
            unset($data['id'], $data['asset_id'], $data['serial_number'], $data['qr_code_path'], $data['created_at'], $data['updated_at'], $data['deleted_at']);
            // Allow user to override fields before save
            $data = array_merge($data, $request->only(array_keys($data)));
            $data['asset_id'] = 'AST-' . strtoupper(uniqid());
            $data['serial_number'] = $request->input('serial_number'); // Must be provided
            $newAsset = Asset::create($data);
            // Copy tags
            $newAsset->tags()->sync($asset->tags->pluck('id')->toArray());
            // Copy images (just references, not files)
            foreach ($asset->images as $img) {
                $newAsset->images()->create(['image_path' => $img->image_path, 'caption' => $img->caption]);
            }
            // Generate QR code
            $qrPath = $this->qrCodeService->generateAssetQRCode($newAsset);
            if ($qrPath) {
                $newAsset->qr_code_path = $qrPath;
                $newAsset->save();
            }
            // Log activity
            $newAsset->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'duplicated',
                'after' => $newAsset->toArray(),
                'comment' => 'Asset duplicated',
            ]);
            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Asset duplicated successfully',
                'data' => $newAsset->load(['category', 'assetType', 'assetStatus', 'department', 'tags', 'images', 'location', 'user', 'company'])
            ], 201);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate asset',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Bulk import assets
    public function bulkImport(BulkImportAssetRequest $request)
    {
        \DB::beginTransaction();
        try {
            $file = $request->file('file');
            $ext = $file->getClientOriginalExtension();
            $rows = [];
            if (in_array($ext, ['csv'])) {
                $rows = array_map('str_getcsv', file($file->getRealPath()));
                $header = array_map('trim', array_shift($rows));
                $rows = array_map(function($row) use ($header) {
                    return array_combine($header, $row);
                }, $rows);
            } else {
                // For xlsx/xls, use Laravel Excel if available
                if (!class_exists('Maatwebsite\\Excel\\Facades\\Excel')) {
                    throw new \Exception('Excel import not supported. Install maatwebsite/excel.');
                }
                $rows = \Maatwebsite\Excel\Facades\Excel::toArray(null, $file)[0];
                $header = array_map('trim', array_shift($rows));
                $rows = array_map(function($row) use ($header) {
                    return array_combine($header, $row);
                }, $rows);
            }
            $imported = [];
            $errors = [];
            foreach ($rows as $i => $row) {
                try {
                    $row['asset_id'] = 'AST-' . strtoupper(uniqid());
                    $row['company_id'] = $request->user()->company_id;
                    $asset = Asset::create($row);
                    $imported[] = $asset;
                } catch (\Exception $e) {
                    $errors[] = ['row' => $i + 2, 'error' => $e->getMessage()];
                }
            }
            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Bulk import completed',
                'imported_count' => count($imported),
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk import assets from array payload
     * Route: POST /api/assets/import-bulk
     * Payload: { assets: [ ... ] }
     */
    public function importBulk(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $assets = $request->input('assets', []);
        $errors = [];
        $imported = 0;
        if (!is_array($assets) || empty($assets)) {
            return response()->json([
                'imported' => 0,
                'errors' => [['row' => 0, 'error' => 'The assets field is required and must be a non-empty array.']]
            ], 422);
        }
        // Collect all serial numbers in DB for this company for uniqueness check
        $existingSerials = \App\Models\Asset::where('company_id', $companyId)
            ->pluck('serial_number')->filter()->map(fn($s) => strtolower($s))->toArray();
        $serialsInPayload = [];
        foreach ($assets as $i => $row) {
            $rowErrors = [];
            // Validate required fields
            if (empty($row['name'])) {
                $rowErrors[] = 'Asset name is required.';
            }
            // Validate purchase_cost
            if (isset($row['purchase_cost']) && !is_numeric($row['purchase_cost'])) {
                $rowErrors[] = 'Purchase cost must be numeric.';
            }
            // Validate purchase_date
            if (!empty($row['purchase_date'])) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['purchase_date']) || strtotime($row['purchase_date']) === false) {
                    $rowErrors[] = 'Purchase date must be in YYYY-MM-DD format.';
                } elseif (strtotime($row['purchase_date']) > time()) {
                    $rowErrors[] = 'Purchase date cannot be in the future.';
                }
            }
            // Validate serial_number uniqueness (in DB and in this payload)
            $serial = isset($row['serial_number']) ? strtolower($row['serial_number']) : null;
            if ($serial) {
                if (in_array($serial, $existingSerials)) {
                    $rowErrors[] = 'Serial number already exists in this company.';
                }
                if (in_array($serial, $serialsInPayload)) {
                    $rowErrors[] = 'Duplicate serial number in import file.';
                }
                $serialsInPayload[] = $serial;
            }
            // Lookup or create asset type
            $typeId = null;
            if (!empty($row['asset_type'])) {
                $type = \App\Models\AssetType::firstOrCreate(
                    ['name' => $row['asset_type']],
                    ['icon' => 'https://unpkg.com/lucide-static/icons/tag.svg']
                );
                $typeId = $type->id;
            }
            // Lookup or create category
            $categoryId = null;
            if (!empty($row['category'])) {
                $category = \App\Models\AssetCategory::firstOrCreate(['name' => $row['category']]);
                $categoryId = $category->id;
            }
            // Lookup or create department
            $departmentId = null;
            if (!empty($row['department'])) {
                $department = \App\Models\Department::where('company_id', $companyId)
                    ->where('name', $row['department'])->first();
                if (!$department) {
                    $department = \App\Models\Department::create([
                        'name' => $row['department'],
                        'company_id' => $companyId,
                        'user_id' => $user->id,
                        'is_active' => true
                    ]);
                }
                $departmentId = $department->id;
            }
            // Lookup location by full path or name (do not create)
            $locationId = null;
            if (!empty($row['location'])) {
                $location = \App\Models\Location::where('company_id', $companyId)->get()
                    ->first(fn($loc) => $loc->full_path === $row['location'] || $loc->name === $row['location']);
                if (!$location) {
                    $rowErrors[] = 'Location not found: ' . $row['location'];
                } else {
                    $locationId = $location->id;
                }
            }
            // If any errors, collect and skip
            if (!empty($rowErrors)) {
                $errors[] = ['row' => $i + 1, 'error' => implode(' ', $rowErrors)];
                continue;
            }
            // Generate unique asset_id
            $assetId = 'ASSET-' . $companyId . '-' . strtoupper(substr(uniqid(), -6));
            // Prepare asset data
            $assetData = [
                'asset_id' => $assetId,
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'category_id' => $categoryId,
                'type' => $row['asset_type'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'model' => $row['model'] ?? null,
                'manufacturer' => $row['manufacturer'] ?? null,
                'purchase_date' => $row['purchase_date'] ?? null,
                'purchase_price' => $row['purchase_cost'] ?? null,
                'location_id' => $locationId,
                'department_id' => $departmentId,
                'user_id' => $user->id,
                'company_id' => $companyId,
                'status' => $row['status'] ?? 'active',
                'warranty' => $row['warranty_period'] ?? null,
                'health_score' => $row['health_score'] ?? null,
                'brand' => $row['brand'] ?? null,
                'supplier' => $row['supplier'] ?? null,
                'depreciation' => $row['depreciation_method'] ?? null,
            ];
            \DB::beginTransaction();
            try {
                $asset = \App\Models\Asset::create($assetData);
                // Handle tags
                if (!empty($row['tags']) && is_array($row['tags'])) {
                    $tagIds = [];
                    foreach ($row['tags'] as $tagName) {
                        $tag = \App\Models\AssetTag::firstOrCreate(['name' => $tagName]);
                        $tagIds[] = $tag->id;
                    }
                    $asset->tags()->sync($tagIds);
                }
                // Generate QR code
                $qrPath = $this->qrCodeService->generateAssetQRCode($asset);
                if ($qrPath) {
                    $asset->qr_code_path = $qrPath;
                    $asset->save();
                }
                \DB::commit();
                $imported++;
            } catch (\Exception $e) {
                \DB::rollBack();
                $errors[] = ['row' => $i + 1, 'error' => $e->getMessage()];
            }
        }
        return response()->json([
            'imported' => $imported,
            'errors' => $errors
        ]);
    }

    /**
     * Bulk import assets from JSON payload
     * Route: POST /api/assets/import-bulk-json
     * Payload: { assets: [ ... ] }
     */
    public function bulkImportAssets(Request $request)
    {
        // Validate the request
        $request->validate([
            'assets' => 'required|array|min:1',
            'assets.*.name' => 'required|string|max:100',
            'assets.*.description' => 'nullable|string|max:500',
            'assets.*.asset_description' => 'nullable|string|max:500',
            'assets.*.category' => 'nullable|string|max:255',
            'assets.*.s_m_type' => 'nullable|string|max:255',
            'assets.*.type' => 'nullable|string|max:255',
            'assets.*.serial_number' => 'nullable|string|max:255|unique:assets,serial_number,NULL,id,company_id,' . ($request->user() ? $request->user()->company_id : 'NULL'),
            'assets.*.model' => 'nullable|string|max:255',
            'assets.*.manufacturer' => 'nullable|string|max:255',
            'assets.*.brand_make' => 'nullable|string|max:255',
            'assets.*.capacity' => 'nullable|string|max:255',
            'assets.*.purchase_date' => 'nullable|date|before_or_equal:today',
            'assets.*.purchase_price' => 'nullable|numeric|min:0',
            'assets.*.depreciation' => 'nullable|numeric',
            'assets.*.building' => 'nullable|string|max:255',
            'assets.*.location' => 'nullable|string|max:255',
            'assets.*.floor' => 'nullable|string|max:255',
            'assets.*.location_id' => 'nullable|integer|exists:locations,id',
            'assets.*.department' => 'nullable|string|max:255',
            'assets.*.warranty' => 'nullable|string|max:255',
            'assets.*.insurance' => 'nullable|string|max:255',
            'assets.*.health_score' => 'nullable|numeric|min:0|max:100',
            'assets.*.status' => 'nullable|string|max:50',
            'assets.*.tags' => 'nullable|array',
            'assets.*.tags.*' => 'string|max:255',
        ]);

        $user = $request->user();
        $assets = $request->input('assets');
        $imported = [];
        $failed = [];
        $errors = [];

        \DB::beginTransaction();
        try {
            foreach ($assets as $index => $assetData) {
                try {
                    // Find or create category
                    $category = null;
                    if (!empty($assetData['category'])) {
                        $category = AssetCategory::firstOrCreate(
                            ['name' => $assetData['category']],
                            [
                                'description' => $assetData['category'] . ' category',
                                'icon' => 'https://unpkg.com/lucide-static/icons/package.svg'
                            ]
                        );
                    }

                    // Find or create S/M Type as asset category
                    $smTypeCategory = null;
                    if (!empty($assetData['s_m_type'])) {
                        $smTypeCategory = AssetCategory::firstOrCreate(
                            ['name' => $assetData['s_m_type']],
                            [
                                'description' => $assetData['s_m_type'] . ' S/M Type category',
                                'icon' => 'https://unpkg.com/lucide-static/icons/thumbs-up.svg'
                            ]
                        );
                    }

                    // Handle hierarchical location structure
                    $location = null;
                    $parentLocation = null;
                    
                    // If building is provided, create or find building location
                    if (!empty($assetData['building'])) {
                        $parentLocation = Location::firstOrCreate(
                            [
                                'name' => $assetData['building'],
                                'company_id' => $user->company_id,
                                'parent_id' => null, // Building is top level
                                'hierarchy_level' => 0
                            ],
                            [
                                'user_id' => $user->id,
                                'description' => 'Building: ' . $assetData['building'],
                                'icon' => 'https://unpkg.com/lucide-static/icons/building-2.svg',
                                'qr_code_path' => null,
                                'address' => null,
                                'location_type_id' => null
                            ]
                        );
                    }
                    
                    // If location is provided, create or find location under building
                    if (!empty($assetData['location'])) {
                        $location = Location::firstOrCreate(
                            [
                                'name' => $assetData['location'],
                                'company_id' => $user->company_id,
                                'parent_id' => $parentLocation ? $parentLocation->id : null,
                                'hierarchy_level' => 1
                            ],
                            [
                                'user_id' => $user->id,
                                'description' => 'Location: ' . $assetData['location'],
                                'icon' => 'https://unpkg.com/lucide-static/icons/map-pin.svg',
                                'qr_code_path' => null,
                                'address' => null,
                                'location_type_id' => null
                            ]
                        );
                        
                        // Update parent location reference
                        $parentLocation = $location;
                    }
                    
                                        // If floor is provided, create or find floor under location
                    if (!empty($assetData['floor'])) {
                        $location = Location::firstOrCreate(
                            [
                                'name' => $assetData['floor'],
                                'company_id' => $user->company_id,
                                'parent_id' => $parentLocation ? $parentLocation->id : null,
                                'hierarchy_level' => 2
                            ],
                            [
                                'user_id' => $user->id,
                                'description' => 'Floor: ' . $assetData['floor'],
                                'icon' => 'https://unpkg.com/lucide-static/icons/layers.svg',
                                'qr_code_path' => null,
                                'address' => null,
                                'location_type_id' => null
                            ]
                        );
                    }

                    // Find or create department
                    $department = null;
                    if (!empty($assetData['department'])) {
                        $department = Department::firstOrCreate(
                            [
                                'name' => $assetData['department'],
                                'company_id' => $user->company_id,
                                'user_id' => $user->id
                            ],
                        );
                    }

                    // Find or create asset type
                    $assetType = null;
                    if (!empty($assetData['type'])) {
                        $assetType = AssetType::firstOrCreate(
                            ['name' => $assetData['type']],
                            [
                                'icon' => 'https://unpkg.com/lucide-static/icons/tag.svg'
                            ]
                        );
                    }

                    // Find or create asset status
                    $assetStatus = null;
                    if (!empty($assetData['status'])) {
                        $assetStatus = AssetStatus::firstOrCreate(
                            ['name' => $assetData['status']],
                            [
                                'description' => $assetData['status'] . ' status',
                                'color' => $assetData['status'] === 'Active' ? '#10B981' : '#6B7280'
                            ]
                        );
                    }

                    // Generate unique asset ID
                    $assetId = 'AST-' . strtoupper(substr($user->company_id, 0, 3)) . '-' .
                              str_pad(Asset::where('company_id', $user->company_id)->count() + 1, 4, '0', STR_PAD_LEFT);

                    // Create the asset
                    $asset = Asset::create([
                        'asset_id' => $assetId,
                        'name' => $assetData['name'],
                        'description' => $assetData['asset_description'] ?? $assetData['description'] ?? '',
                        'category_id' => $category?->id ?? $smTypeCategory?->id,
                        'type' => $assetType?->name ?? $assetData['type'] ?? null,
                        'serial_number' => $assetData['serial_number'] ?? null,
                        'model' => $assetData['model'] ?? null,
                        'manufacturer' => $assetData['brand_make'] ?? $assetData['manufacturer'] ?? null,
                        'capacity' => $assetData['capacity'] ?? null,
                        'purchase_date' => $assetData['purchase_date'] ?? null,
                        'purchase_price' => $assetData['purchase_price'] ?? null,
                        'depreciation' => $assetData['depreciation'] ?? null,
                        'location_id' => $location?->id ?? null,
                        'department_id' => $department?->id,
                        'user_id' => $user->id,
                        'company_id' => $user->company_id,
                        'warranty' => $assetData['warranty'] ?? null,
                        'insurance' => $assetData['insurance'] ?? null,
                        'health_score' => $assetData['health_score'] ?? 100,
                        'status' => $assetData['status'] ?? 'active',
                    ]);

                    // Handle tags if provided
                    if (!empty($assetData['tags']) && is_array($assetData['tags'])) {
                        $tagIds = [];
                        foreach ($assetData['tags'] as $tagName) {
                            $tag = AssetTag::firstOrCreate(
                                ['name' => $tagName],
                                ['company_id' => $user->company_id]
                            );
                            $tagIds[] = $tag->id;
                        }
                        $asset->tags()->attach($tagIds);
                    }

                    // Log activity
                    $asset->activities()->create([
                        'user_id' => $user->id,
                        'action' => 'created',
                        'before' => null,
                        'after' => $asset->toArray(),
                        'comment' => 'Asset imported via bulk import',
                    ]);

                    $imported[] = [
                        'index' => $index + 1,
                        'asset_id' => $asset->asset_id,
                        'name' => $asset->name,
                        'status' => 'success'
                    ];

                } catch (\Exception $e) {
                    $failed[] = [
                        'index' => $index + 1,
                        'name' => $assetData['name'] ?? 'Unknown',
                        'error' => $e->getMessage()
                    ];
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk import completed',
                'data' => [
                    'total_processed' => count($assets),
                    'imported_count' => count($imported),
                    'failed_count' => count($failed),
                    'imported' => $imported,
                    'failed' => $failed,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Bulk import failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download asset import template
     * Route: GET /api/assets/import/template
     */
    public function downloadTemplate()
    {
        $templatePath = public_path('asset-import-template.xlsx');

        if (!file_exists($templatePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Template file not found. Please contact administrator.'
            ], 404);
        }

        $filename = 'asset-import-template-' . date('Y-m-d') . '.xlsx';

        return response()->download($templatePath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache'
        ]);
    }

    // Transfer asset
    public function transfer(TransferAssetRequest $request, Asset $asset)
    {
        // Check if asset belongs to user's company
        if ($asset->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found or unauthorized'
            ], 404);
        }

        // Check if new location is different from current
        if ($asset->location_id == $request->new_location_id) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer location must be different from current.'
            ], 400);
        }

        \DB::beginTransaction();
        try {
            $before = $asset->toArray();

            // Update asset location and department
            $asset->location_id = $request->new_location_id;
            if ($request->filled('new_department_id')) {
                $asset->department_id = $request->new_department_id;
            }
            if ($request->filled('to_user_id')) {
                $asset->user_id = $request->to_user_id;
            }
            $asset->save();

            // Create transfer record
            $transfer = $asset->transfers()->create([
                'old_location_id' => $before['location_id'],
                'new_location_id' => $request->new_location_id,
                'old_department_id' => $before['department_id'],
                'new_department_id' => $request->new_department_id,
                'from_user_id' => $before['user_id'],
                'to_user_id' => $request->to_user_id,
                'reason' => $request->transfer_reason,
                'transfer_date' => $request->transfer_date,
                'notes' => $request->notes,
                'condition_report' => $request->condition_report,
                'status' => 'completed',
                'approved_by' => $request->user()->id,
                'created_by' => $request->user()->id,
            ]);

            // Log activity
            $asset->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'transferred',
                'before' => $before,
                'after' => $asset->toArray(),
                'comment' => 'Asset transferred: ' . $request->transfer_reason,
            ]);

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asset transfer completed.',
                'data' => [
                    'transfer_id' => $transfer->id,
                    'asset_id' => $asset->asset_id,
                    'new_location' => $asset->location->name ?? null,
                    'new_department' => $asset->department->name ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Asset transfer failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Debug method to test validation rules
    public function debugTransferValidation(Request $request)
    {
        $transferRequest = new TransferAssetRequest();
        $rules = $transferRequest->rules();

        return response()->json([
            'validation_rules' => $rules,
            'request_data' => $request->all(),
            'expected_fields' => array_keys($rules)
        ]);
    }

    // Maintenance schedule CRUD
    public function addMaintenanceSchedule(MaintenanceScheduleRequest $request, Asset $asset)
    {
        $schedule = $asset->maintenanceSchedules()->create($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Maintenance schedule added',
            'data' => $schedule
        ], 201);
    }

    public function updateMaintenanceSchedule(MaintenanceScheduleRequest $request, Asset $asset, $scheduleId)
    {
        $schedule = $asset->maintenanceSchedules()->findOrFail($scheduleId);
        $schedule->update($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Maintenance schedule updated',
            'data' => $schedule
        ]);
    }

    public function deleteMaintenanceSchedule(Request $request, Asset $asset, $scheduleId)
    {
        $schedule = $asset->maintenanceSchedules()->findOrFail($scheduleId);
        $schedule->delete();
        return response()->json([
            'success' => true,
            'message' => 'Maintenance schedule deleted',
        ]);
    }

    public function listMaintenanceSchedules(Request $request, Asset $asset)
    {
        $schedules = $asset->maintenanceSchedules()->orderBy('next_due', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $schedules
        ]);
    }

    // Activity history
    /**
     * Get asset activity history with filtering, search, and pagination
     * Route: GET /api/assets/{asset}/activity-history
     */
    public function activityHistory(Request $request, Asset $asset)
    {
        // Check if user has access to this asset
        if ($asset->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $query = $asset->activities()->with(['user', 'asset']);

        // Search by action or comment
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%$search%")
                  ->orWhere('comment', 'like', "%$search%")
                  ->orWhereHas('user', function ($userQ) use ($search) {
                      $userQ->where('name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%");
                  });
            });
        }

        // Filter by action type
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['created_at', 'action', 'user_id'];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $activities = $query->paginate($perPage);

        // Transform the data to include more readable information
        $activities->getCollection()->transform(function ($activity) {
            return [
                'id' => $activity->id,
                'action' => $activity->action,
                'comment' => $activity->comment,
                'before' => $activity->before,
                'after' => $activity->after,
                'user' => $activity->user ? [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                    'email' => $activity->user->email
                ] : null,
                'created_at' => $activity->created_at,
                'updated_at' => $activity->updated_at,
                'formatted_date' => $activity->created_at->format('M d, Y H:i:s'),
                'time_ago' => $activity->created_at->diffForHumans()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $activities,
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'serial_number' => $asset->serial_number
            ]
        ]);
    }

    /**
     * Public API to get assets with optional company filtering
     * Route: GET /api/assets/public
     */
    public function publicIndex(Request $request)
    {
        $query = Asset::with(['category', 'assetType', 'assetStatus', 'department', 'tags', 'images', 'location', 'company'])
            ->where('status', 'active')
            ->withoutTrashed();

        // Filter by company if provided
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by company slug if provided
        if ($request->filled('company_slug')) {
            $query->whereHas('company', function ($q) use ($request) {
                $q->where('slug', $request->company_slug);
            });
        }

        // Search functionality
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('serial_number', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                  ->orWhereHas('tags', function ($tagQ) use ($search) {
                      $tagQ->where('name', 'like', "%$search%");
                  })
                  ->orWhereHas('category', function ($catQ) use ($search) {
                      $catQ->where('name', 'like', "%$search%");
                  });
            });
        }

        // Filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('id', $request->tag_id);
            });
        }
        if ($request->filled('min_value')) {
            $query->where('purchase_price', '>=', $request->min_value);
        }
        if ($request->filled('max_value')) {
            $query->where('purchase_price', '<=', $request->max_value);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['name', 'serial_number', 'purchase_price', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $assets = $query->paginate($perPage);

        // Transform the data to include only public information
        $assets->getCollection()->transform(function ($asset) {
            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'serial_number' => $asset->serial_number,
                'description' => $asset->description,
                'model' => $asset->model,
                'manufacturer' => $asset->manufacturer,
                'purchase_date' => $asset->purchase_date,
                'purchase_price' => $asset->purchase_price,
                'warranty' => $asset->warranty,
                'health_score' => $asset->health_score,
                'status' => $asset->status,
                'created_at' => $asset->created_at,
                'updated_at' => $asset->updated_at,
                'category' => $asset->category ? [
                    'id' => $asset->category->id,
                    'name' => $asset->category->name,
                    'description' => $asset->category->description
                ] : null,
                'asset_type' => $asset->assetType ? [
                    'id' => $asset->assetType->id,
                    'name' => $asset->assetType->name,
                    'description' => $asset->assetType->description
                ] : null,
                'asset_status' => $asset->assetStatus ? [
                    'id' => $asset->assetStatus->id,
                    'name' => $asset->assetStatus->name,
                    'color' => $asset->assetStatus->color
                ] : null,
                'location' => $asset->location ? [
                    'id' => $asset->location->id,
                    'name' => $asset->location->name,
                    'address' => $asset->location->address
                ] : null,
                'department' => $asset->department ? [
                    'id' => $asset->department->id,
                    'name' => $asset->department->name
                ] : null,
                'company' => $asset->company ? [
                    'id' => $asset->company->id,
                    'name' => $asset->company->name,
                    'slug' => $asset->company->slug
                ] : null,
                'tags' => $asset->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color
                    ];
                }),
                'images' => $asset->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => \Storage::disk('public')->url($image->path),
                        'alt' => $image->alt_text
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'assets' => $assets->items(),
                'pagination' => [
                    'current_page' => $assets->currentPage(),
                    'last_page' => $assets->lastPage(),
                    'per_page' => $assets->perPage(),
                    'total' => $assets->total(),
                    'from' => $assets->firstItem(),
                    'to' => $assets->lastItem(),
                ],
                'filters' => $request->all(),
            ]
        ]);
    }

    /**
     * Public API to get a specific asset by ID
     * Route: GET /api/assets/{asset}/public
     */
    public function publicShow($id): \Illuminate\Http\JsonResponse
    {
        $asset = Asset::find($id);
        if (!$asset) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found'
            ], 404);
        }

        $asset->load(['category', 'assetType', 'assetStatus', 'department', 'tags', 'images', 'location', 'company']);

        $assetData = [
            'id' => $asset->id,
            'name' => $asset->name,
            'serial_number' => $asset->serial_number,
            'description' => $asset->description,
            'model' => $asset->model,
            'manufacturer' => $asset->manufacturer,
            'purchase_date' => $asset->purchase_date,
            'purchase_price' => $asset->purchase_price,
            'warranty' => $asset->warranty,
            'health_score' => $asset->health_score,
            'status' => $asset->status,
            'created_at' => $asset->created_at,
            'updated_at' => $asset->updated_at,
            'category' => $asset->category ? [
                'id' => $asset->category->id,
                'name' => $asset->category->name,
                'description' => $asset->category->description
            ] : null,
            'asset_type' => $asset->assetType ? [
                'id' => $asset->assetType->id,
                'name' => $asset->assetType->name,
                'description' => $asset->assetType->description
            ] : null,
            'asset_status' => $asset->assetStatus ? [
                'id' => $asset->assetStatus->id,
                'name' => $asset->assetStatus->name,
                'color' => $asset->assetStatus->color
            ] : null,
            'location' => $asset->location ? [
                'id' => $asset->location->id,
                'name' => $asset->location->name,
                'address' => $asset->location->address,
                'type' => $asset->location->type ? [
                    'id' => $asset->location->type->id,
                    'name' => $asset->location->type->name
                ] : null
            ] : null,
            'department' => $asset->department ? [
                'id' => $asset->department->id,
                'name' => $asset->department->name
            ] : null,
            'company' => $asset->company ? [
                'id' => $asset->company->id,
                'name' => $asset->company->name,
                'slug' => $asset->company->slug
            ] : null,
            'tags' => $asset->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color
                ];
            }),
            'images' => $asset->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => \Storage::disk('public')->url($image->path),
                    'alt' => $image->alt_text
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'asset' => $assetData,
            ]
        ]);
    }

    /**
     * Get all asset activities across the company with filtering and pagination
     * Route: GET /api/assets/activities
     */
    public function allActivities(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = AssetActivity::with(['user', 'asset'])
            ->whereHas('asset', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });

        // Search by action, comment, asset name, or user
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%$search%")
                  ->orWhere('comment', 'like', "%$search%")
                  ->orWhereHas('asset', function ($assetQ) use ($search) {
                      $assetQ->where('name', 'like', "%$search%")
                             ->orWhere('serial_number', 'like', "%$search%");
                  })
                  ->orWhereHas('user', function ($userQ) use ($search) {
                      $userQ->where('name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%");
                  });
            });
        }

        // Filter by action type
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by asset
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSortFields = ['created_at', 'action', 'user_id', 'asset_id'];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $activities = $query->paginate($perPage);

        // Transform the data to include more readable information
        $activities->getCollection()->transform(function ($activity) {
            return [
                'id' => $activity->id,
                'action' => $activity->action,
                'comment' => $activity->comment,
                'before' => $activity->before,
                'after' => $activity->after,
                'asset' => $activity->asset ? [
                    'id' => $activity->asset->id,
                    'name' => $activity->asset->name,
                    'serial_number' => $activity->asset->serial_number
                ] : null,
                'user' => $activity->user ? [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                    'email' => $activity->user->email
                ] : null,
                'created_at' => $activity->created_at,
                'updated_at' => $activity->updated_at,
                'formatted_date' => $activity->created_at->format('M d, Y H:i:s'),
                'time_ago' => $activity->created_at->diffForHumans()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    /**
     * Get asset statistics (total, active, maintenance, value, health)
     */
    public function statistics(Request $request)
    {
        $companyId = $request->user()->company_id;

        // Basic asset counts
        $totalAssets = Asset::where('company_id', $companyId)->count();
        $activeAssets = Asset::where('company_id', $companyId)->where('status', 'active')->count();
        $inactiveAssets = Asset::where('company_id', $companyId)->where('status', '!=', 'active')->count();

        $maintenanceStatus = AssetStatus::where('name', 'Maintenance')->first();
        $maintenanceAssets = Asset::where('company_id', $companyId)
            ->where('status', $maintenanceStatus->id) // Maintenance status
            ->count();


        // Financial statistics
        $totalValue = Asset::where('company_id', $companyId)->sum('purchase_price');
        $totalHealth = Asset::where('company_id', $companyId)->sum('health_score');
        $averageHealth = $totalAssets > 0 ? round($totalHealth / $totalAssets, 2) : 0;



        // Category breakdown
        $categoryBreakdown = Asset::where('company_id', $companyId)
            ->join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
            ->selectRaw('asset_categories.name, COUNT(*) as count')
            ->groupBy('asset_categories.id', 'asset_categories.name')
            ->pluck('count', 'name')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                // Basic counts
                'total_assets' => $totalAssets,
                'active_assets' => $activeAssets,
                'inactive_assets' => $inactiveAssets,

                // Maintenance statistics
                'maintenance' => $maintenanceAssets,

                // Financial statistics
                'total_asset_value' => $totalValue,
                'total_asset_health' => $totalHealth,
                'average_health_score' => $averageHealth,

                // Breakdowns
                'category_breakdown' => $categoryBreakdown,

                // Legacy field for backward compatibility
                'maintenance_count' => $maintenanceAssets, // Now correctly shows assets under maintenance
            ]
        ]);
    }

    /**
     * Public API to get asset statistics
     * Route: GET /api/assets/public/statistics
     */
    public function publicStatistics(Request $request)
    {
        // Filter by company if provided
        $companyId = null;
        if ($request->filled('company_id')) {
            $companyId = $request->company_id;
        } elseif ($request->filled('company_slug')) {
            $company = \App\Models\Company::where('slug', $request->company_slug)->first();
            if ($company) {
                $companyId = $company->id;
            }
        }

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company ID or slug is required'
            ], 400);
        }

        // Basic asset counts (only active assets for public API)
        $totalAssets = Asset::where('company_id', $companyId)->where('status', 'active')->count();

        // Maintenance count - assets with status "Maintenance" (asset_status_id = 2) but still active
        $maintenanceAssets = Asset::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('asset_status_id', 2) // Maintenance status
            ->count();

        // Maintenance schedules statistics
        $maintenanceSchedules = \App\Models\AssetMaintenanceSchedule::whereHas('asset', function($q) use ($companyId) {
            $q->where('company_id', $companyId)->where('status', 'active');
        });

        $totalMaintenanceSchedules = $maintenanceSchedules->count();
        $activeMaintenanceSchedules = $maintenanceSchedules->where('status', 'active')->count();
        $overdueMaintenanceSchedules = $maintenanceSchedules->where('next_due', '<', now())->count();

        // Assets with maintenance schedules
        $assetsWithMaintenance = Asset::where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('maintenanceSchedules')
            ->count();

        // Financial statistics
        $totalValue = Asset::where('company_id', $companyId)->where('status', 'active')->sum('purchase_price');
        $totalHealth = Asset::where('company_id', $companyId)->where('status', 'active')->sum('health_score');
        $averageHealth = $totalAssets > 0 ? round($totalHealth / $totalAssets, 2) : 0;

        // Category breakdown
        $categoryBreakdown = Asset::where('company_id', $companyId)
            ->where('status', 'active')
            ->join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
            ->selectRaw('asset_categories.name, COUNT(*) as count')
            ->groupBy('asset_categories.id', 'asset_categories.name')
            ->pluck('count', 'name')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => $companyId,
                'total_assets' => $totalAssets,
                'maintenance' => [
                    'assets_under_maintenance' => $maintenanceAssets, // Assets with status "Maintenance"
                    'total_schedules' => $totalMaintenanceSchedules,
                    'active_schedules' => $activeMaintenanceSchedules,
                    'overdue_schedules' => $overdueMaintenanceSchedules,
                    'assets_with_maintenance' => $assetsWithMaintenance,
                ],
                'total_asset_value' => $totalValue,
                'average_health_score' => $averageHealth,
                'category_breakdown' => $categoryBreakdown,
            ]
        ]);
    }

    /**
     * Get analytics for archived and active assets
     * Route: GET /api/assets/analytics
     */
    public function analytics(Request $request)
    {
        $companyId = $request->user()->company_id;
        $totalAssets = \App\Models\Asset::withTrashed()->where('company_id', $companyId)->count();
        $activeAssets = \App\Models\Asset::where('company_id', $companyId)->where('status', 'active')->count();
        $archivedAssets = \App\Models\Asset::onlyTrashed()->where('company_id', $companyId)->count();
        $archivedByMonth = \App\Models\Asset::onlyTrashed()
            ->where('company_id', $companyId)
            ->selectRaw('YEAR(deleted_at) as year, MONTH(deleted_at) as month, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        return response()->json([
            'success' => true,
            'data' => [
                'total_assets' => $totalAssets,
                'active_assets' => $activeAssets,
                'archived_assets' => $archivedAssets,
                'archived_by_month' => $archivedByMonth,
            ]
        ]);
    }

    /**
     * Export assets (optionally only archived) as CSV
     * Route: GET /api/assets/export?archived=1
     */
    public function export(Request $request)
    {
        $companyId = $request->user()->company_id;
        $archived = $request->boolean('archived', false);
        $query = \App\Models\Asset::query()->where('company_id', $companyId);
        if ($archived) {
            $query->onlyTrashed();
        }
        $assets = $query->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="assets_export.csv"',
        ];
        $columns = [
            'id', 'asset_id', 'name', 'description', 'category_id', 'type', 'serial_number', 'model', 'manufacturer',
            'capacity', 'purchase_date', 'purchase_price', 'depreciation', 'location_id', 'department_id', 'user_id', 'company_id',
            'warranty', 'insurance', 'health_score', 'status', 'archive_reason', 'deleted_at', 'created_at', 'updated_at'
        ];
        $callback = function() use ($assets, $columns) {
            $file = fopen('php://output', 'w');
            
            // Custom headers with "Capacity/Rating" instead of "capacity"
            $headers = [
                'id', 'asset_id', 'name', 'description', 'category_id', 'type', 'serial_number', 'model', 'manufacturer',
                'Capacity/Rating', 'purchase_date', 'purchase_price', 'depreciation', 'location_id', 'department_id', 'user_id', 'company_id',
                'warranty', 'insurance', 'health_score', 'status', 'archive_reason', 'deleted_at', 'created_at', 'updated_at'
            ];
            
            fputcsv($file, $headers);
            foreach ($assets as $asset) {
                $row = [];
                foreach ($columns as $col) {
                    $row[] = $asset->$col ?? '';
                }
                fputcsv($file, $row);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export assets to Excel with custom format
     */
    public function exportExcel(Request $request)
    {
        $companyId = $request->user()->company_id;
        
        // Get all assets for the company without any conditions or pagination
        $assets = Asset::with(['category', 'location.parent.parent'])
            ->where('company_id', $companyId)
            ->withoutTrashed()
            ->get();

        // Prepare data for Excel export
        $exportData = [];
        
        // Add headers
        $exportData[] = [
            'Asset ID Number',
            'S/M Type',
            'Building',
            'Location', 
            'Floor',
            'Asset Description',
            'Brand/Make',
            'Model No',
            'Capacity/Rating'
        ];

        // Process each asset
        foreach ($assets as $asset) {
            // Determine location hierarchy
            $building = '';
            $location = '';
            $floor = '';
            
            if ($asset->location) {
                $locationModel = $asset->location;
                $ancestors = $locationModel->ancestors();
                $ancestorCount = $ancestors->count();
                
                if ($ancestorCount == 0) {
                    // No parent - location is building
                    $building = $locationModel->name;
                } elseif ($ancestorCount == 1) {
                    // 1 parent - parent is building, current is location
                    $building = $ancestors->first()->name;
                    $location = $locationModel->name;
                } elseif ($ancestorCount >= 2) {
                    // 2+ parents - 1st parent is building, 2nd is location, current is floor
                    $building = $ancestors->first()->name;
                    $location = $ancestors->get(1)->name;
                    $floor = $locationModel->name;
                }
            }

            $exportData[] = [
                $asset->asset_id ?? '',
                $asset->category->name ?? '',
                $building,
                $location,
                $floor,
                $asset->description ?? '',
                $asset->manufacturer ?? $asset->serial_number ?? '',
                $asset->model ?? '',
                '' // Capacity/Rating - not available in current model, left empty
            ];
        }

        // Create temporary file for Excel export
        $fileName = 'assets_export_' . date('Y_m_d_H_i_s') . '.xlsx';
        
        try {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\AssetsExcelExport($exportData),
                $fileName
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate Excel export: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk archive (soft delete) assets
     */
    public function bulkArchive(Request $request)
    {
        $request->validate([
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:assets,id',
            'archive_reason' => 'nullable|string',
        ]);

        $userId = $request->user()->id;
        $assetIds = $request->asset_ids;
        $archiveReason = $request->archive_reason;
        $success = [];
        $failed = [];

        foreach ($assetIds as $id) {
            $asset = \App\Models\Asset::find($id);
            if (!$asset) {
                $failed[] = [
                    'id' => $id,
                    'reason' => 'Asset not found',
                ];
                continue;
            }
            if ($asset->transfers()->where('status', 'pending')->exists()) {
                $failed[] = [
                    'id' => $id,
                    'reason' => 'Active transfers',
                ];
                continue;
            }
            if ($asset->maintenanceSchedules()->where('status', 'active')->exists()) {
                $failed[] = [
                    'id' => $id,
                    'reason' => 'Active maintenance',
                ];
                continue;
            }
            try {
                $before = $asset->toArray();
                // Set archive_reason if provided and set status to archived
                if ($archiveReason) {
                    $asset->archive_reason = $archiveReason;
                }
                $asset->status = 'archived';
                $asset->save();
                $asset->delete();
                $asset->activities()->create([
                    'user_id' => $userId,
                    'action' => 'archived',
                    'before' => $before,
                    'after' => null,
                    'comment' => 'Asset archived (bulk)',
                ]);
                $success[] = $id;
            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $id,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'archived' => $success,
            'failed' => $failed,
        ]);
    }

    /**
     * Bulk permanently delete (force delete) assets that are already archived
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:assets,id',
        ]);

        $userId = $request->user()->id;
        $assetIds = $request->asset_ids;
        $success = [];
        $failed = [];

        foreach ($assetIds as $id) {
            $asset = \App\Models\Asset::withTrashed()->find($id);
            if (!$asset) {
                $failed[] = [
                    'id' => $id,
                    'reason' => 'Asset not found',
                ];
                continue;
            }
            if (!$asset->trashed()) {
                $failed[] = [
                    'id' => $id,
                    'reason' => 'Asset is not archived',
                ];
                continue;
            }
            try {
                $before = $asset->toArray();
                if ($request->filled('deletion_reason')) {
                    $asset->deletion_reason = $request->deletion_reason;
                    $asset->save();
                }
                // Delete QR code file if exists
                if ($asset->qr_code_path) {
                    $this->qrCodeService->deleteQRCode($asset->qr_code_path);
                }
                $asset->forceDelete();
                $asset->activities()->create([
                    'user_id' => $userId,
                    'action' => 'deleted',
                    'before' => $before,
                    'after' => null,
                    'comment' => 'Asset permanently deleted (bulk)',
                ]);
                $success[] = $id;
            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $id,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'deleted' => $success,
            'failed' => $failed,
        ]);
    }

    /**
     * Restore a soft-deleted (archived) asset
     * Route: POST /api/assets/{asset}/restore
     */
    public function restore(Request $request, $id)
    {
        $asset = \App\Models\Asset::withTrashed()->findOrFail($id);
        if (!$asset->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Asset is not archived.'
            ], 400);
        }
        \DB::beginTransaction();
        try {
            $before = $asset->toArray();
            $asset->restore();
            // Optionally clear archive_reason
            $asset->archive_reason = null;
            $asset->save();
            // Log activity
            $asset->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'restored',
                'before' => $before,
                'after' => $asset->toArray(),
                'comment' => 'Asset restored from archive',
            ]);
            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Asset restored successfully',
                'data' => $asset->fresh()
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore asset',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk restore archived assets
     * Route: POST /api/assets/bulk-restore
     * Body: { "asset_ids": [1,2,3] }
     */
    public function bulkRestore(Request $request)
    {
        $request->validate([
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:assets,id',
        ]);
        $userId = $request->user()->id;
        $assetIds = $request->asset_ids;
        $restored = [];
        $failed = [];
        foreach ($assetIds as $id) {
            $asset = \App\Models\Asset::withTrashed()->find($id);
            if (!$asset || !$asset->trashed()) {
                $failed[] = [
                    'id' => $id,
                    'reason' => !$asset ? 'Asset not found' : 'Asset is not archived',
                ];
                continue;
            }
            try {
                $before = $asset->toArray();
                $asset->restore();
                $asset->archive_reason = null;
                $asset->save();
                $asset->activities()->create([
                    'user_id' => $userId,
                    'action' => 'restored',
                    'before' => $before,
                    'after' => $asset->toArray(),
                    'comment' => 'Asset restored from archive (bulk)',
                ]);
                $restored[] = $id;
            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $id,
                    'reason' => $e->getMessage(),
                ];
            }
        }
        return response()->json([
            'success' => true,
            'restored' => $restored,
            'failed' => $failed,
        ]);
    }

    /**
     * Get asset hierarchy (tree structure)
     */
    public function hierarchy(Request $request)
    {
        $companyId = $request->user()->company_id;

        $assets = Asset::with(['children', 'category', 'assetType', 'assetStatus'])
            ->forCompany($companyId)
            ->rootAssets()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'hierarchy' => $this->buildAssetHierarchyTree($assets)
            ]
        ]);
    }

    /**
     * Get possible parents for an asset
     */
    public function possibleParents(Request $request, $assetId = null)
    {
        $companyId = $request->user()->company_id;
        $query = Asset::forCompany($companyId);

        if ($assetId) {
            $asset = Asset::find($assetId);
            if ($asset) {
                // Exclude the asset itself and its descendants
                $excludeIds = collect([$asset->id])->merge($asset->descendants->pluck('id'));
                $query->whereNotIn('id', $excludeIds);
            }
        }

        $assets = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'possible_parents' => $assets->map(function ($asset) {
                    return [
                        'id' => $asset->id,
                        'name' => $asset->name,
                        'asset_id' => $asset->asset_id,
                        'full_path' => $asset->full_path,
                    ];
                })
            ]
        ]);
    }

    /**
     * Move asset to new parent
     */
    public function move(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'new_parent_id' => 'nullable|exists:assets,id',
        ]);

        $asset = Asset::findOrFail($request->asset_id);

        // Check if user has permission to modify this asset
        if ($asset->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this asset.'
            ], 403);
        }

        // Check for circular reference
        if ($asset->wouldCreateCircularReference($request->new_parent_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot move asset: would create circular reference.'
            ], 400);
        }

        $before = $asset->toArray();
        $asset->parent_id = $request->new_parent_id;
        $asset->save();

        // Log activity
        $asset->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'moved',
            'before' => $before,
            'after' => $asset->toArray(),
            'comment' => 'Asset moved in hierarchy',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asset moved successfully',
            'data' => $asset->load(['parent', 'children'])
        ]);
    }

    /**
     * Build asset hierarchy tree
     */
    private function buildAssetHierarchyTree($assets)
    {
        return $assets->map(function ($asset) {
            return [
                'id' => $asset->id,
                'asset_id' => $asset->asset_id,
                'name' => $asset->name,
                'full_path' => $asset->full_path,
                'category' => $asset->category ? $asset->category->name : null,
                'type' => $asset->assetType ? $asset->assetType->name : null,
                'status' => $asset->assetStatus ? $asset->assetStatus->name : null,
                'has_children' => $asset->has_children,
                'children' => $this->buildAssetHierarchyTree($asset->children),
            ];
        });
    }

    /**
     * Get QR code for an asset (direct QuickChart.io URL)
     * Route: GET /api/assets/{asset}/qr-code
     */
    public function qrCode(Request $request, Asset $asset)
    {
        // Check if user has access to this asset
        if ($asset->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'qr_code_url' => $asset->quick_chart_qr_url,
                'public_url' => $asset->public_url,
                'asset_id' => $asset->id,
                'asset_name' => $asset->name
            ]
        ]);
    }

    /**
     * Generate barcode for an asset using asset_id
     * Route: GET /api/assets/{asset}/barcode
     */
    public function barcode(Request $request, Asset $asset)
    {
        try {
            // Check if user has access to this asset
            if ($asset->company_id !== $request->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            $type = $request->get('type', 'code128');
            $width = $request->get('width', 300);
            $height = $request->get('height', 100);

            // Build QuickChart.io URL for barcode
            $barcodeUrl = $this->buildBarcodeUrl($asset->asset_id, $type, $width, $height);

            return response()->json([
                'success' => true,
                'data' => [
                    'asset_id' => $asset->id,
                    'asset_name' => $asset->name,
                    'asset_identifier' => $asset->asset_id,
                    'barcode_type' => $type,
                    'barcode_url' => $barcodeUrl,
                    'width' => $width,
                    'height' => $height,
                    'available_types' => $this->getAvailableBarcodeTypes()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate barcode: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available barcode types
     * Route: GET /api/assets/barcode-types
     */
    public function barcodeTypes()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'barcode_types' => $this->getAvailableBarcodeTypes()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get barcode types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build barcode URL using a reliable barcode service
     */
    private function buildBarcodeUrl($text, $type = 'code128', $width = 300, $height = 100)
    {
        // Use Barcode.tec-it.com API which is more reliable for barcodes
        $baseUrl = 'https://barcode.tec-it.com/barcode.ashx';
        
        // Build URL manually to avoid double encoding issues
        $url = $baseUrl . '?data=' . urlencode($text) . 
               '&code=' . urlencode($type) . 
               '&multiplebarcodes=false' .
               '&translate-esc=false' .
               '&unit=Fit' .
               '&dpi=96' .
               '&imagetype=Png' .
               '&rotation=0' .
               '&color=%23000000' .
               '&bgcolor=%23ffffff' .
               '&codepage=Default' .
               '&qunit=0' .
               '&quiet=0' .
               '&hidehrt=False' .
               '&width=' . $width .
               '&height=' . $height;

        return $url;
    }

    /**
     * Get available barcode types
     */
    private function getAvailableBarcodeTypes()
    {
        return [
            'code128' => 'Code 128 (Most common, supports alphanumeric)',
            'code39' => 'Code 39 (Alphanumeric, widely used)',
            'ean13' => 'EAN-13 (13-digit product codes)',
            'ean8' => 'EAN-8 (8-digit product codes)',
            'upca' => 'UPC-A (12-digit product codes)',
            'upce' => 'UPC-E (6-digit product codes)',
            'itf14' => 'ITF-14 (14-digit shipping codes)',
            'datamatrix' => 'Data Matrix (2D barcode)',
            'qr' => 'QR Code (2D barcode)',
        ];
    }

    /**
     * Get related assets for a specific asset
     * Route: GET /api/assets/{asset}/related
     */
    public function relatedAssets(Request $request, Asset $asset)
    {
        try {
            // Check if user has access to this asset
            if ($asset->company_id !== $request->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            $limit = $request->get('limit', 10);
            $type = $request->get('type', 'all'); // all, category, location, department, manufacturer, parent, children

            $query = Asset::where('company_id', $request->user()->company_id)
                ->where('id', '!=', $asset->id) // Exclude the current asset
                ->where('status', 'active'); // Only active assets

            // Apply different filtering based on type
            switch ($type) {
                case 'category':
                    $query->where('category_id', $asset->category_id);
                    break;
                    
                case 'location':
                    $query->where('location_id', $asset->location_id);
                    break;
                    
                case 'department':
                    $query->where('department_id', $asset->department_id);
                    break;
                    
                case 'manufacturer':
                    if ($asset->manufacturer) {
                        $query->where('manufacturer', $asset->manufacturer);
                    }
                    break;
                    
                case 'parent':
                    // Assets that have the same parent
                    $query->where('parent_id', $asset->parent_id);
                    break;
                    
                case 'children':
                    // Child assets of the current asset
                    $query->where('parent_id', $asset->id);
                    break;
                    
                case 'siblings':
                    // Assets with the same parent (excluding the current asset)
                    if ($asset->parent_id) {
                        $query->where('parent_id', $asset->parent_id);
                    }
                    break;
                    
                case 'similar':
                    // Assets with similar characteristics
                    $query->where(function($q) use ($asset) {
                        $q->where('category_id', $asset->category_id)
                          ->orWhere('location_id', $asset->location_id)
                          ->orWhere('department_id', $asset->department_id)
                          ->orWhere('manufacturer', $asset->manufacturer);
                    });
                    break;
                    
                case 'all':
                default:
                    // Get assets with any relation
                    $query->where(function($q) use ($asset) {
                        $q->where('category_id', $asset->category_id)
                          ->orWhere('location_id', $asset->location_id)
                          ->orWhere('department_id', $asset->department_id)
                          ->orWhere('manufacturer', $asset->manufacturer)
                          ->orWhere('parent_id', $asset->parent_id)
                          ->orWhere('parent_id', $asset->id);
                    });
                    break;
            }

            // Load relationships
            $relatedAssets = $query->with([
                'category:id,name',
                'assetType:id,name',
                'assetStatus:id,name,color',
                'department:id,name',
                'location:id,name',
                'company:id,name',
                'tags:id,name',
                'images:id,asset_id,image_path'
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

            // Transform the data
            $transformedAssets = $relatedAssets->map(function ($relatedAsset) {
                return [
                    'id' => $relatedAsset->id,
                    'asset_id' => $relatedAsset->asset_id,
                    'name' => $relatedAsset->name,
                    'description' => $relatedAsset->description,
                    'serial_number' => $relatedAsset->serial_number,
                    'model' => $relatedAsset->model,
                    'manufacturer' => $relatedAsset->manufacturer,
                    'purchase_price' => $relatedAsset->purchase_price,
                    'health_score' => $relatedAsset->health_score,
                    'status' => $relatedAsset->status,
                    'created_at' => $relatedAsset->created_at,
                    'updated_at' => $relatedAsset->updated_at,
                    'category' => $relatedAsset->category ? [
                        'id' => $relatedAsset->category->id,
                        'name' => $relatedAsset->category->name,
                    ] : null,
                    'asset_type' => $relatedAsset->assetType ? [
                        'id' => $relatedAsset->assetType->id,
                        'name' => $relatedAsset->assetType->name,
                    ] : null,
                    'asset_status' => $relatedAsset->assetStatus ? [
                        'id' => $relatedAsset->assetStatus->id,
                        'name' => $relatedAsset->assetStatus->name,
                        'color' => $relatedAsset->assetStatus->color,
                    ] : null,
                    'department' => $relatedAsset->department ? [
                        'id' => $relatedAsset->department->id,
                        'name' => $relatedAsset->department->name,
                    ] : null,
                    'location' => $relatedAsset->location ? [
                        'id' => $relatedAsset->location->id,
                        'name' => $relatedAsset->location->name,
                    ] : null,
                    'company' => $relatedAsset->company ? [
                        'id' => $relatedAsset->company->id,
                        'name' => $relatedAsset->company->name,
                    ] : null,
                    'tags' => $relatedAsset->tags->map(function ($tag) {
                        return [
                            'id' => $tag->id,
                            'name' => $tag->name,
                        ];
                    }),
                    'images' => $relatedAsset->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'image_path' => $image->image_path,
                            'image_url' => \Storage::disk('public')->url($image->image_path),
                        ];
                    }),
                    'qr_code_url' => $relatedAsset->quick_chart_qr_url,
                    'barcode_url' => $this->buildBarcodeUrl($relatedAsset->asset_id, 'code128', 200, 80),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'current_asset' => [
                        'id' => $asset->id,
                        'name' => $asset->name,
                        'asset_id' => $asset->asset_id,
                    ],
                    'related_assets' => $transformedAssets,
                    'total_count' => $relatedAssets->count(),
                    'filter_type' => $type,
                    'limit' => $limit,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get related assets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get asset chart data (depreciation chart)
     * Route: GET /api/assets/{asset}/chart-data
     */
    public function chartData(Request $request, Asset $asset)
    {
        try {
            // Check if user has access to this asset
            if ($asset->company_id !== $request->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // Calculate asset chart
            $depreciationMonths = [];
            $depreciationValues = [];
            $currentIndex = '';

            if (
                is_numeric($asset->purchase_price) &&
                is_numeric($asset->depreciation) &&
                is_numeric($asset->depreciation_life) &&
                $asset->depreciation_life > 0
            ) {
                $purchaseDate = date('Y-m', strtotime($asset->created_at));

                // Ensure asset life is at least 1 to prevent division by zero
                $lifeInMonths = max(1, $asset->depreciation_life - 1);

                $startDate = strtotime($purchaseDate);
                $endDate = strtotime("+$lifeInMonths months", $startDate);

                $depreciationPerMonth = ($asset->purchase_price - $asset->depreciation) / $lifeInMonths;
                $remainingValue = $asset->purchase_price;

                $depreciationValues[] = $remainingValue;
                $depreciationMonths[] = 1;

                $index = 2;
                $monthIndex = 0;

                while ($startDate < $endDate) {
                    // Track current month index
                    if (date('Y-m') === date('Y-m', $startDate)) {
                        $currentIndex = $monthIndex;
                    }

                    $remainingValue -= $depreciationPerMonth;

                    $depreciationMonths[] = $index;
                    $depreciationValues[] = $remainingValue;

                    $startDate = strtotime('+1 month', $startDate);
                    $index++;
                    $monthIndex++;
                }

                // If asset life has ended, set index to last
                if (time() > $endDate) {
                    $currentIndex = $monthIndex;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'asset' => [
                        'id' => $asset->id,
                        'name' => $asset->name,
                        'asset_id' => $asset->asset_id,
                        'purchase_price' => $asset->purchase_price,
                        'depreciation' => $asset->depreciation,
                        'depreciation_life' => $asset->depreciation_life,
                        'created_at' => $asset->created_at,
                    ],
                    'chart_data' => [
                        'depreciation_months' => $depreciationMonths,
                        'depreciation_values' => $depreciationValues,
                        'current_index' => $currentIndex,
                        'has_data' => !empty($depreciationMonths),
                        'total_months' => count($depreciationMonths),
                        'depreciation_per_month' => isset($depreciationPerMonth) ? $depreciationPerMonth : null,
                        'life_in_months' => isset($lifeInMonths) ? $lifeInMonths : null,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get chart data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get asset health & performance chart data
     * Route: GET /api/assets/{asset}/health-performance-chart
     */
    public function healthPerformanceChart(Request $request, Asset $asset)
    {
        try {
            // Check if user has access to this asset
            if ($asset->company_id !== $request->user()->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // Get time range from request (default to last 12 months)
            $months = $request->get('months', 12);
            $months = min(max($months, 1), 60); // Limit between 1 and 60 months

            // Calculate health & performance data
            $healthData = [];
            $performanceData = [];
            $maintenanceData = [];
            $dates = [];
            $currentIndex = '';

            // Get asset activities for health tracking
            $activities = $asset->activities()
                ->where('created_at', '>=', now()->subMonths($months))
                ->orderBy('created_at', 'asc')
                ->get();

            // Get maintenance schedules for performance tracking
            $maintenanceSchedules = $asset->maintenanceSchedules()
                ->where('created_at', '>=', now()->subMonths($months))
                ->orderBy('created_at', 'asc')
                ->get();

            // Generate monthly data points
            $startDate = now()->subMonths($months)->startOfMonth();
            $endDate = now()->endOfMonth();
            $currentDate = $startDate->copy();

            $monthIndex = 0;
            $baseHealthScore = $asset->health_score ?? 100;
            $currentHealthScore = $baseHealthScore;

            while ($currentDate <= $endDate) {
                $monthKey = $currentDate->format('Y-m');
                $dates[] = $monthKey;

                // Calculate health score based on activities and maintenance
                $monthActivities = $activities->filter(function ($activity) use ($currentDate) {
                    return $activity->created_at->format('Y-m') === $currentDate->format('Y-m');
                });

                $monthMaintenance = $maintenanceSchedules->filter(function ($schedule) use ($currentDate) {
                    return $schedule->created_at->format('Y-m') === $currentDate->format('Y-m');
                });

                // Health score calculation
                $healthImpact = 0;
                foreach ($monthActivities as $activity) {
                    switch ($activity->action) {
                        case 'maintenance_completed':
                            $healthImpact += 5; // Positive impact
                            break;
                        case 'maintenance_overdue':
                            $healthImpact -= 10; // Negative impact
                            break;
                        case 'repair':
                            $healthImpact -= 15; // Significant negative impact
                            break;
                        case 'inspection_passed':
                            $healthImpact += 3; // Small positive impact
                            break;
                        case 'inspection_failed':
                            $healthImpact -= 8; // Negative impact
                            break;
                        default:
                            $healthImpact += 0; // No impact
                    }
                }

                // Performance calculation based on maintenance schedules
                $performanceScore = 100;
                foreach ($monthMaintenance as $schedule) {
                    if ($schedule->status === 'completed') {
                        $performanceScore += 5;
                    } elseif ($schedule->status === 'overdue') {
                        $performanceScore -= 15;
                    } elseif ($schedule->status === 'scheduled') {
                        $performanceScore += 2;
                    }
                }

                // Apply health impact
                $currentHealthScore = max(0, min(100, $currentHealthScore + $healthImpact));

                // Natural degradation over time (small decrease per month)
                $currentHealthScore = max(0, $currentHealthScore - 0.5);

                $healthData[] = round($currentHealthScore, 1);
                $performanceData[] = max(0, min(100, $performanceScore));
                $maintenanceData[] = $monthMaintenance->count();

                // Track current month index
                if ($currentDate->format('Y-m') === now()->format('Y-m')) {
                    $currentIndex = $monthIndex;
                }

                $currentDate->addMonth();
                $monthIndex++;
            }

            // Calculate performance metrics
            $avgHealthScore = count($healthData) > 0 ? round(array_sum($healthData) / count($healthData), 1) : 0;
            $avgPerformanceScore = count($performanceData) > 0 ? round(array_sum($performanceData) / count($performanceData), 1) : 0;
            $totalMaintenanceCount = array_sum($maintenanceData);
            $healthTrend = count($healthData) > 1 ? ($healthData[count($healthData) - 1] - $healthData[0]) : 0;
            $performanceTrend = count($performanceData) > 1 ? ($performanceData[count($performanceData) - 1] - $performanceData[0]) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'asset' => [
                        'id' => $asset->id,
                        'name' => $asset->name,
                        'asset_id' => $asset->asset_id,
                        'health_score' => $asset->health_score,
                        'status' => $asset->status,
                        'created_at' => $asset->created_at,
                    ],
                    'chart_data' => [
                        'dates' => $dates,
                        'health_scores' => $healthData,
                        'performance_scores' => $performanceData,
                        'maintenance_counts' => $maintenanceData,
                        'current_index' => $currentIndex,
                        'has_data' => !empty($healthData),
                        'total_months' => count($dates),
                        'metrics' => [
                            'average_health_score' => $avgHealthScore,
                            'average_performance_score' => $avgPerformanceScore,
                            'total_maintenance_count' => $totalMaintenanceCount,
                            'health_trend' => $healthTrend,
                            'performance_trend' => $performanceTrend,
                            'current_health_score' => end($healthData) ?: $asset->health_score,
                            'current_performance_score' => end($performanceData) ?: 100,
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get health & performance chart data: ' . $e->getMessage()
            ], 500);
        }
    }
}
