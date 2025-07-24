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
use App\Models\AssetMaintenanceSchedule;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    // List assets (grid/list view)
    public function index(Request $request)
    {
        $query = Asset::with(['category', 'tags', 'images', 'location', 'user', 'company']);

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
        $asset->load(['category', 'tags', 'images', 'location', 'user', 'company', 'maintenanceSchedules', 'activities']);
        return response()->json([
            'success' => true,
            'data' => [
                'asset' => $asset,
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
                $asset->tags()->sync($request->tags);
            }

            // Handle images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('assets/images', 'public');
                    $asset->images()->create(['image_path' => $path]);
                }
            }

            // Generate QR code (using QRCodeService)
            $qrService = app(\App\Services\QRCodeService::class);
            $qrContent = route('assets.show', $asset->id); // Or use a public URL if available
            $qrPath = $qrService->generateLocationQRCode((object)[
                'id' => $asset->id,
                'public_url' => $qrContent,
            ]);
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
                'data' => $asset->load(['category', 'tags', 'images', 'location', 'user', 'company'])
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
                $asset->tags()->sync($request->tags);
            }

            // Handle images (add only, for simplicity)
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('assets/images', 'public');
                    $asset->images()->create(['image_path' => $path]);
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
                'data' => $asset->load(['category', 'tags', 'images', 'location', 'user', 'company'])
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
            $qrService = app(\App\Services\QRCodeService::class);
            $qrContent = route('assets.show', $newAsset->id);
            $qrPath = $qrService->generateLocationQRCode((object)[
                'id' => $newAsset->id,
                'public_url' => $qrContent,
            ]);
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
                'data' => $newAsset->load(['category', 'tags', 'images', 'location', 'user', 'company'])
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

    // Transfer asset
    public function transfer(TransferAssetRequest $request, Asset $asset)
    {
        \DB::beginTransaction();
        try {
            $before = $asset->toArray();
            $asset->location_id = $request->to_location_id;
            if ($request->filled('to_user_id')) {
                $asset->user_id = $request->to_user_id;
            }
            $asset->save();
            $transfer = $asset->transfers()->create([
                'from_location_id' => $before['location_id'],
                'to_location_id' => $request->to_location_id,
                'from_user_id' => $before['user_id'],
                'to_user_id' => $request->to_user_id,
                'transfer_date' => $request->transfer_date,
                'notes' => $request->notes,
                'condition_report' => $request->condition_report,
                'status' => 'completed',
                'approved_by' => $request->user()->id,
            ]);
            $asset->activities()->create([
                'user_id' => $request->user()->id,
                'action' => 'transferred',
                'before' => $before,
                'after' => $asset->toArray(),
                'comment' => 'Asset transferred',
            ]);
            \DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Asset transferred successfully',
                'data' => [
                    'asset' => $asset->load(['location', 'user']),
                    'transfer' => $transfer,
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
            $q->where('company_id', $companyId);
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
}
