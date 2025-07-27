<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use Illuminate\Http\Request;

class AssetTypeController extends Controller
{
    /**
     * Display a listing of asset types.
     */
    public function index(Request $request)
    {
        $query = AssetType::query();

        // Search
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%$search%");
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $assetTypes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'asset_types' => $assetTypes->items(),
                'pagination' => [
                    'current_page' => $assetTypes->currentPage(),
                    'last_page' => $assetTypes->lastPage(),
                    'per_page' => $assetTypes->perPage(),
                    'total' => $assetTypes->total(),
                    'from' => $assetTypes->firstItem(),
                    'to' => $assetTypes->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Display the specified asset type.
     */
    public function show(AssetType $assetType)
    {
        $assetType->load('assets');
        return response()->json([
            'success' => true,
            'data' => [
                'asset_type' => $assetType,
            ]
        ]);
    }

    /**
     * Store a newly created asset type.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_types,name',
            'icon' => 'required|string|max:500',
        ]);

        $assetType = AssetType::create($request->only(['name', 'icon']));

        return response()->json([
            'success' => true,
            'message' => 'Asset type created successfully',
            'data' => $assetType
        ], 201);
    }

    /**
     * Update the specified asset type.
     */
    public function update(Request $request, AssetType $assetType)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_types,name,' . $assetType->id,
            'icon' => 'required|string|max:500',
        ]);

        $assetType->update($request->only(['name', 'icon']));

        return response()->json([
            'success' => true,
            'message' => 'Asset type updated successfully',
            'data' => $assetType
        ]);
    }

    /**
     * Remove the specified asset type.
     */
    public function destroy(AssetType $assetType)
    {
        // Check if asset type is being used by any assets
        if ($assetType->assets()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete asset type that is being used by assets.'
            ], 400);
        }

        $assetType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset type deleted successfully',
        ]);
    }

    /**
     * Get all asset types for dropdown/select.
     */
    public function list()
    {
        $assetTypes = AssetType::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $assetTypes
        ]);
    }
} 