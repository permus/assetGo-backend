<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetStatus;
use Illuminate\Http\Request;

class AssetStatusController extends Controller
{
    /**
     * Display a listing of asset statuses.
     */
    public function index(Request $request)
    {
        $query = AssetStatus::query();

        // Filter by active status
        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        // Search
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%$search%");
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $statuses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => $statuses->items(),
                'pagination' => [
                    'current_page' => $statuses->currentPage(),
                    'last_page' => $statuses->lastPage(),
                    'per_page' => $statuses->perPage(),
                    'total' => $statuses->total(),
                    'from' => $statuses->firstItem(),
                    'to' => $statuses->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Display the specified asset status.
     */
    public function show(AssetStatus $assetStatus)
    {
        $assetStatus->load('assets');
        return response()->json([
            'success' => true,
            'data' => [
                'status' => $assetStatus,
            ]
        ]);
    }

    /**
     * Store a newly created asset status.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_statuses,name',
            'color' => 'required|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $status = AssetStatus::create($request->only(['name', 'color', 'description', 'is_active', 'sort_order']));

        return response()->json([
            'success' => true,
            'message' => 'Asset status created successfully',
            'data' => $status
        ], 201);
    }

    /**
     * Update the specified asset status.
     */
    public function update(Request $request, AssetStatus $assetStatus)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_statuses,name,' . $assetStatus->id,
            'color' => 'required|string|max:7|regex:/^#[0-9A-F]{6}$/i',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $assetStatus->update($request->only(['name', 'color', 'description', 'is_active', 'sort_order']));

        return response()->json([
            'success' => true,
            'message' => 'Asset status updated successfully',
            'data' => $assetStatus
        ]);
    }

    /**
     * Remove the specified asset status.
     */
    public function destroy(AssetStatus $assetStatus)
    {
        // Check if status is being used by any assets
        if ($assetStatus->assets()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete status that is being used by assets.'
            ], 400);
        }

        $assetStatus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset status deleted successfully',
        ]);
    }

    /**
     * Get all active asset statuses for dropdown/select.
     */
    public function list()
    {
        $statuses = AssetStatus::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $statuses
        ]);
    }
} 