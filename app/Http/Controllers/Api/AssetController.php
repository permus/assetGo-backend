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
use App\Models\AssetTag;
use App\Models\AssetImage;
use App\Models\AssetTransfer;
use App\Models\AssetActivity;
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
            $assetData['status'] = $data['status'] ?? 'active';
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
                $type = \App\Models\AssetType::firstOrCreate(['name' => $row['asset_type']]);
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
     * Download asset import template
     * Route: GET /api/assets/import/template
     */
    public function downloadTemplate()
    {
        $templatePath = public_path('asset-import-template.xlsx');

        if (!file_exists($templatePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Template file not found'
            ], 404);
        }

        return response()->download($templatePath, 'asset-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="asset-import-template.xlsx"'
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
    public function activityHistory(Request $request, Asset $asset)
    {
        $activities = $asset->activities()->with('user')->orderBy('created_at', 'desc')->get();
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
        $totalAssets = Asset::where('company_id', $companyId)->count();
        $activeAssets = Asset::where('company_id', $companyId)->where('status', 'active')->count();
        $maintenanceCount = \App\Models\AssetMaintenanceSchedule::whereHas('asset', function($q) use ($companyId) {
            $q->withTrashed()->where('company_id', $companyId);
        })->where('status', 'active')->count();
        $totalValue = Asset::where('company_id', $companyId)->sum('purchase_price');
        $totalHealth = Asset::where('company_id', $companyId)->sum('health_score');

        return response()->json([
            'success' => true,
            'data' => [
                'total_assets' => $totalAssets,
                'active_assets' => $activeAssets,
                'maintenance' => $maintenanceCount,
                'total_asset_value' => $totalValue,
                'total_asset_health' => $totalHealth,
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
            'purchase_date', 'purchase_price', 'depreciation', 'location_id', 'department_id', 'user_id', 'company_id',
            'warranty', 'insurance', 'health_score', 'status', 'archive_reason', 'deleted_at', 'created_at', 'updated_at'
        ];
        $callback = function() use ($assets, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
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
}
