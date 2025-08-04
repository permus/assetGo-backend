<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Requests\Location\BulkCreateLocationRequest;
use App\Http\Requests\Location\MoveLocationRequest;
use App\Models\Location;
use App\Models\LocationType;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocationController extends Controller
{
    protected $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Display a listing of locations with filtering and pagination
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = min($request->get('per_page', 15), 50);

        $query = Location::with(['type', 'parent', 'creator', 'assetSummary'])
            ->forCompany($user->company_id)
            ->search($request->get('search'))
            ->byType($request->get('type_id'))
            ->byParent($request->get('parent_id'))
            ->byHierarchyLevel($request->get('hierarchy_level'));

        // Handle sorting
        $sortBy = $request->get('sort_by', 'hierarchy_level');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? strtolower($sortDirection) : 'asc';
        
        // Handle different sort fields
        switch ($sortBy) {
            case 'created':
                $query->orderBy('created_at', $sortDirection);
                break;
            case 'updated':
                $query->orderBy('updated_at', $sortDirection);
                break;
            case 'name':
                $query->orderBy('name', $sortDirection);
                break;
            case 'hierarchy_level':
            default:
                $query->orderBy('hierarchy_level', $sortDirection)
                      ->orderBy('name', 'asc'); // Secondary sort by name
                break;
        }

        $locations = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'locations' => $locations->items(),
                'pagination' => [
                    'current_page' => $locations->currentPage(),
                    'last_page' => $locations->lastPage(),
                    'per_page' => $locations->perPage(),
                    'total' => $locations->total(),
                    'from' => $locations->firstItem(),
                    'to' => $locations->lastItem(),
                ],
                'filters' => [
                    'search' => $request->get('search'),
                    'type_id' => $request->get('type_id'),
                    'parent_id' => $request->get('parent_id'),
                    'hierarchy_level' => $request->get('hierarchy_level'),
                ],
                'sorting' => [
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection,
                ]
            ]
        ]);
    }

    /**
     * Store a newly created location
     */
    public function store(StoreLocationRequest $request)
    {
        try {
            DB::beginTransaction();

            $location = Location::create([
                'company_id' => $request->user()->company_id,
                'user_id' => $request->user()->id,
                'name' => $request->name,
                'location_type_id' => $request->location_type_id,
                'parent_id' => $request->parent_id,
                'address' => $request->address,
                'description' => $request->description,
                'slug' => $request->slug,
            ]);

            // Generate QR code
            $qrPath = $this->qrCodeService->generateLocationQRCode($location);
            if ($qrPath) {
                $location->update(['qr_code_path' => $qrPath]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Location created successfully',
                'data' => [
                    'location' => $location->load(['type', 'parent', 'creator', 'assetSummary']),
                    'asset_summary' => $location->getAssetSummaryData()
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified location
     */
    public function show(Request $request, Location $location)
    {
        // Check company ownership
        if ($location->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        $location->load([
            'type',
            'parent.type',
            'children.type',
            'creator',
            'assetSummary'
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'location' => $location,
                'ancestors' => $location->ancestors(),
                'children_count' => $location->children()->count(),
                'descendants_count' => $location->descendants()->count(),
            ]
        ]);
    }

    /**
     * Update the specified location
     */
    public function update(UpdateLocationRequest $request, Location $location)
    {
        try {
            DB::beginTransaction();

            $oldName = $location->name;
            $location->update($request->validated());

            // Regenerate QR code if name changed
            if ($request->name && $request->name !== $oldName) {
                $qrPath = $this->qrCodeService->regenerateLocationQRCode($location);
                if ($qrPath) {
                    $location->update(['qr_code_path' => $qrPath]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => [
                    'location' => $location->fresh()->load(['type', 'parent', 'creator', 'assetSummary']),
                    'asset_summary' => $location->getAssetSummaryData()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified location
     */
    public function destroy(Request $request, Location $location)
    {
        // Check company ownership
        if ($location->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        // Check for children
        $childrenCount = $location->children()->count();
        if ($childrenCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete location with {$childrenCount} child location(s). Please move or delete child locations first.",
                'data' => [
                    'children_count' => $childrenCount,
                    'children' => $location->children()->select('id', 'name')->get()
                ]
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Delete QR codes
            $this->qrCodeService->deleteAllQRCodes($location);

            // Delete location (asset summary will be deleted via cascade)
            $location->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Location deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create locations (max 5)
     */
    public function bulkCreate(BulkCreateLocationRequest $request)
    {
        try {
            DB::beginTransaction();

            $locations = [];
            $locationData = $request->input('locations');
            $nameCount = [];

            foreach ($locationData as $data) {
                // Handle duplicate names by adding "copy" suffix
                $originalName = $data['name'];
                $nameCount[$originalName] = ($nameCount[$originalName] ?? 0) + 1;
                
                if ($nameCount[$originalName] > 1) {
                    $data['name'] = $originalName . ' copy';
                    if ($nameCount[$originalName] > 2) {
                        $data['name'] = $originalName . ' copy ' . ($nameCount[$originalName] - 1);
                    }
                }

                $location = Location::create([
                    'company_id' => $request->user()->company_id,
                    'user_id' => $request->user()->id,
                    'name' => $data['name'],
                    'location_type_id' => $data['location_type_id'],
                    'parent_id' => $data['parent_id'] ?? null,
                    'address' => $data['address'] ?? null,
                    'description' => $data['description'] ?? null,
                ]);

                // Generate QR code
                $qrPath = $this->qrCodeService->generateLocationQRCode($location);
                if ($qrPath) {
                    $location->update(['qr_code_path' => $qrPath]);
                }

                $locations[] = $location;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($locations) . ' locations created successfully',
                'data' => [
                    'locations' => collect($locations)->map(function ($location) {
                        $location->load(['type', 'parent', 'creator', 'assetSummary']);
                        return [
                            ...$location->toArray(),
                            'asset_summary' => $location->getAssetSummaryData()
                        ];
                    })
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Move locations (drag & drop)
     */
    public function move(MoveLocationRequest $request)
    {
        try {
            DB::beginTransaction();

            $locationIds = $request->input('location_ids');
            $newParentId = $request->input('new_parent_id');
            $movedLocations = [];
            $warnings = [];

            foreach ($locationIds as $locationId) {
                $location = Location::find($locationId);
                if (!$location) continue;

                // Check for asset warnings
                $assetSummary = $location->assetSummary;
                if ($assetSummary && $assetSummary->hasSignificantAssets()) {
                    $warnings[] = "Location '{$location->name}' has {$assetSummary->asset_count} assets that will be affected by this move.";
                }

                // Update parent
                $location->update(['parent_id' => $newParentId]);
                $movedLocations[] = $location->fresh()->load(['type', 'parent']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($movedLocations) . ' location(s) moved successfully',
                'data' => [
                    'moved_locations' => $movedLocations,
                    'warnings' => $warnings,
                    'new_parent' => $newParentId ? Location::find($newParentId)->load('type') : null,
                    'asset_summaries' => collect($movedLocations)->mapWithKeys(function ($location) {
                        return [$location->id => $location->getAssetSummaryData()];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to move locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate/download QR code
     */
    public function qrCode(Request $request, Location $location)
    {
        // Check company ownership
        if ($location->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        $action = $request->get('action', 'download'); // download, generate, print
        $format = $request->get('format', 'png'); // png, pdf

        try {
            switch ($action) {
                case 'generate':
                    $qrPath = $this->qrCodeService->regenerateLocationQRCode($location);
                    if ($qrPath) {
                        $location->update(['qr_code_path' => $qrPath]);
                        return response()->json([
                            'success' => true,
                            'message' => 'QR code generated successfully',
                            'data' => [
                                'qr_code_path' => $qrPath,
                                'qr_code_url' => asset('storage/' . $qrPath)
                            ]
                        ]);
                    }
                    break;

                case 'print':
                case 'download':
                    $download = $this->qrCodeService->getQRCodeDownload($location, $format);
                    if ($download) {
                        return $download;
                    }
                    break;
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to process QR code request'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'QR code operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get location hierarchy tree
     */
    public function hierarchy(Request $request)
    {
        $user = $request->user();
        
        $locations = Location::with(['type', 'children.type', 'assetSummary'])
            ->forCompany($user->company_id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'hierarchy' => $this->buildHierarchyTree($locations)
            ]
        ]);
    }

    /**
     * Get location types
     */
    public function types(Request $request)
    {
        $query = LocationType::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Hierarchy level filter
        if ($request->filled('hierarchy_level')) {
            $query->where('hierarchy_level', $request->get('hierarchy_level'));
        }

        $types = $query->orderBy('hierarchy_level')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types,
                'filters' => [
                    'search' => $request->get('search'),
                    'hierarchy_level' => $request->get('hierarchy_level'),
                ]
            ]
        ]);
    }

    /**
     * Get possible parents for a location
     */
    public function possibleParents(Request $request, $locationId = null)
    {
        $user = $request->user();
        $typeId = $request->get('type_id');
        
        if (!$typeId) {
            return response()->json([
                'success' => false,
                'message' => 'Location type is required'
            ], 400);
        }

        $type = LocationType::find($typeId);
        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Location type not found'
            ], 404);
        }

        // Get allowed parent types
        $allowedParentTypes = $type->getAllowedParentTypes();
        
        if ($allowedParentTypes->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'possible_parents' => [],
                    'message' => 'This location type can only be created at root level'
                ]
            ]);
        }

        $query = Location::with(['type'])
            ->forCompany($user->company_id)
            ->whereIn('location_type_id', $allowedParentTypes->pluck('id'))
            ->where('hierarchy_level', '<', 3); // Ensure we don't exceed max depth

        // Exclude self and descendants if editing
        if ($locationId) {
            $location = Location::find($locationId);
            if ($location) {
                $excludeIds = [$locationId];
                $descendants = $location->descendants()->pluck('id')->flatten();
                $excludeIds = array_merge($excludeIds, $descendants->toArray());
                $query->whereNotIn('id', $excludeIds);
            }
        }

        $possibleParents = $query->orderBy('hierarchy_level')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'possible_parents' => $possibleParents,
                'allowed_parent_types' => $allowedParentTypes
            ]
        ]);
    }

    /**
     * Build hierarchy tree recursively
     */
    private function buildHierarchyTree($locations)
    {
        return $locations->map(function ($location) {
            return [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
                'hierarchy_level' => $location->hierarchy_level,
                'asset_summary' => $location->asset_summary,
                'children' => $this->buildHierarchyTree($location->children)
            ];
        });
    }
}