<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Models\Location;
use App\Models\LocationType;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    protected $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Display a listing of locations with search, filtering, and pagination
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $typeId = $request->get('type_id');
        $parentId = $request->get('parent_id');

        $query = Location::forCompany($user->company_id)
            ->with(['locationType', 'parent', 'children'])
            ->search($search)
            ->filterByType($typeId)
            ->filterByParent($parentId)
            ->orderBy('name');

        $locations = $query->paginate($perPage);

        // Add additional data to each location
        $locations->getCollection()->transform(function ($location) {
            return [
                'id' => $location->id,
                'name' => $location->name,
                'description' => $location->description,
                'address' => $location->address,
                'slug' => $location->slug,
                'full_path' => $location->full_path,
                'hierarchy_level' => $location->hierarchy_level,
                'has_children' => $location->hasChildren(),
                'children_count' => $location->children->count(),
                'qr_code_url' => $location->qr_code_url,
                'public_url' => $location->public_url,
                'location_type' => $location->locationType,
                'parent' => $location->parent ? [
                    'id' => $location->parent->id,
                    'name' => $location->parent->name,
                    'full_path' => $location->parent->full_path,
                ] : null,
                'created_at' => $location->created_at,
                'updated_at' => $location->updated_at,
            ];
        });

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
                    'search' => $search,
                    'type_id' => $typeId,
                    'parent_id' => $parentId,
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

            $user = $request->user();

            // Create location
            $location = Location::create([
                'company_id' => $user->company_id,
                'parent_id' => $request->parent_id,
                'location_type_id' => $request->location_type_id,
                'name' => $request->name,
                'description' => $request->description,
                'address' => $request->address,
            ]);

            // Generate QR code
            $qrCodePath = $this->qrCodeService->generateLocationQRCode($location);
            if ($qrCodePath) {
                $location->update(['qr_code_path' => $qrCodePath]);
            }

            DB::commit();

            // Load relationships for response
            $location->load(['locationType', 'parent']);

            return response()->json([
                'success' => true,
                'message' => 'Location created successfully',
                'data' => [
                    'location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'description' => $location->description,
                        'address' => $location->address,
                        'slug' => $location->slug,
                        'full_path' => $location->full_path,
                        'hierarchy_level' => $location->hierarchy_level,
                        'qr_code_url' => $location->qr_code_url,
                        'public_url' => $location->public_url,
                        'location_type' => $location->locationType,
                        'parent' => $location->parent ? [
                            'id' => $location->parent->id,
                            'name' => $location->parent->name,
                            'full_path' => $location->parent->full_path,
                        ] : null,
                        'created_at' => $location->created_at,
                        'updated_at' => $location->updated_at,
                    ]
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
        $user = $request->user();

        // Check if location belongs to user's company
        if ($location->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        $location->load(['locationType', 'parent', 'children.locationType']);

        return response()->json([
            'success' => true,
            'data' => [
                'location' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'description' => $location->description,
                    'address' => $location->address,
                    'slug' => $location->slug,
                    'full_path' => $location->full_path,
                    'hierarchy_level' => $location->hierarchy_level,
                    'has_children' => $location->hasChildren(),
                    'qr_code_url' => $location->qr_code_url,
                    'public_url' => $location->public_url,
                    'location_type' => $location->locationType,
                    'parent' => $location->parent ? [
                        'id' => $location->parent->id,
                        'name' => $location->parent->name,
                        'full_path' => $location->parent->full_path,
                    ] : null,
                    'children' => $location->children->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'name' => $child->name,
                            'location_type' => $child->locationType,
                            'children_count' => $child->children->count(),
                        ];
                    }),
                    'ancestors' => $location->ancestors()->map(function ($ancestor) {
                        return [
                            'id' => $ancestor->id,
                            'name' => $ancestor->name,
                        ];
                    }),
                    'created_at' => $location->created_at,
                    'updated_at' => $location->updated_at,
                ]
            ]
        ]);
    }

    /**
     * Update the specified location
     */
    public function update(UpdateLocationRequest $request, Location $location)
    {
        $user = $request->user();

        // Check if location belongs to user's company
        if ($location->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Update location
            $location->update($request->validated());

            // Regenerate QR code if name changed (since it affects the URL)
            if ($request->filled('name') && $location->wasChanged('name')) {
                $qrCodePath = $this->qrCodeService->regenerateLocationQRCode($location);
                if ($qrCodePath) {
                    $location->update(['qr_code_path' => $qrCodePath]);
                }
            }

            DB::commit();

            // Load relationships for response
            $location->load(['locationType', 'parent']);

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => [
                    'location' => [
                        'id' => $location->id,
                        'name' => $location->name,
                        'description' => $location->description,
                        'address' => $location->address,
                        'slug' => $location->slug,
                        'full_path' => $location->full_path,
                        'hierarchy_level' => $location->hierarchy_level,
                        'qr_code_url' => $location->qr_code_url,
                        'public_url' => $location->public_url,
                        'location_type' => $location->locationType,
                        'parent' => $location->parent ? [
                            'id' => $location->parent->id,
                            'name' => $location->parent->name,
                            'full_path' => $location->parent->full_path,
                        ] : null,
                        'created_at' => $location->created_at,
                        'updated_at' => $location->updated_at,
                    ]
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
        $user = $request->user();

        // Check if location belongs to user's company
        if ($location->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        // Check if location has children
        if ($location->hasChildren()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete location that has child locations. Please delete or move child locations first.',
                'children_count' => $location->children()->count()
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Delete QR code file
            if ($location->qr_code_path) {
                $this->qrCodeService->deleteQRCode($location->qr_code_path);
            }

            // Delete location
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
     * Get location hierarchy tree
     */
    public function hierarchy(Request $request)
    {
        $user = $request->user();

        $locations = Location::forCompany($user->company_id)
            ->whereNull('parent_id')
            ->with(['locationType', 'descendants.locationType'])
            ->orderBy('name')
            ->get();

        $buildTree = function ($locations) use (&$buildTree) {
            return $locations->map(function ($location) use ($buildTree) {
                return [
                    'id' => $location->id,
                    'name' => $location->name,
                    'location_type' => $location->locationType,
                    'children_count' => $location->children()->count(),
                    'children' => $buildTree($location->children),
                ];
            });
        };

        return response()->json([
            'success' => true,
            'data' => [
                'hierarchy' => $buildTree($locations)
            ]
        ]);
    }

    /**
     * Get location types
     */
    public function types()
    {
        $types = LocationType::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types
            ]
        ]);
    }

    /**
     * Get possible parent locations for a location
     */
    public function possibleParents(Request $request, Location $location = null)
    {
        $user = $request->user();

        $query = Location::forCompany($user->company_id)
            ->with('locationType')
            ->where('hierarchy_level', '<', 3); // Max 4 levels, so parents can be at most level 2

        // Exclude the location itself and its descendants if updating
        if ($location) {
            $excludeIds = [$location->id];
            $descendants = $this->getAllDescendantIds($location);
            $excludeIds = array_merge($excludeIds, $descendants);
            $query->whereNotIn('id', $excludeIds);
        }

        $possibleParents = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'possible_parents' => $possibleParents->map(function ($parent) {
                    return [
                        'id' => $parent->id,
                        'name' => $parent->name,
                        'full_path' => $parent->full_path,
                        'location_type' => $parent->locationType,
                        'hierarchy_level' => $parent->hierarchy_level,
                    ];
                })
            ]
        ]);
    }

    /**
     * Download QR code for a location
     */
    public function downloadQRCode(Request $request, Location $location)
    {
        $user = $request->user();

        // Check if location belongs to user's company
        if ($location->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        }

        if (!$location->qr_code_path) {
            return response()->json([
                'success' => false,
                'message' => 'QR code not found for this location'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $location->qr_code_path);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'QR code file not found'
            ], 404);
        }

        return response()->download($filePath, 'qr-code-' . $location->slug . '.png');
    }

    /**
     * Get all descendant IDs recursively
     */
    private function getAllDescendantIds($location)
    {
        $descendants = [];
        $children = Location::where('parent_id', $location->id)->get();
        
        foreach ($children as $child) {
            $descendants[] = $child->id;
            $descendants = array_merge($descendants, $this->getAllDescendantIds($child));
        }
        
        return $descendants;
    }
}