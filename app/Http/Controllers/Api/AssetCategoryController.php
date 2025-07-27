<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    /**
     * Display a listing of asset categories.
     */
    public function index(Request $request)
    {
        $query = AssetCategory::query();

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
        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories->items(),
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'from' => $categories->firstItem(),
                    'to' => $categories->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Display the specified asset category.
     */
    public function show(AssetCategory $assetCategory)
    {
        $assetCategory->load('assets');
        return response()->json([
            'success' => true,
            'data' => [
                'category' => $assetCategory,
            ]
        ]);
    }

    /**
     * Store a newly created asset category.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name',
            'icon' => 'nullable|string|max:500',
            'description' => 'nullable|string',
        ]);

        $category = AssetCategory::create($request->only(['name', 'icon', 'description']));

        return response()->json([
            'success' => true,
            'message' => 'Asset category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Update the specified asset category.
     */
    public function update(Request $request, AssetCategory $assetCategory)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name,' . $assetCategory->id,
            'icon' => 'nullable|string|max:500',
            'description' => 'nullable|string',
        ]);

        $assetCategory->update($request->only(['name', 'icon', 'description']));

        return response()->json([
            'success' => true,
            'message' => 'Asset category updated successfully',
            'data' => $assetCategory
        ]);
    }

    /**
     * Remove the specified asset category.
     */
    public function destroy(AssetCategory $assetCategory)
    {
        // Check if category is being used by any assets
        if ($assetCategory->assets()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category that is being used by assets.'
            ], 400);
        }

        $assetCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asset category deleted successfully',
        ]);
    }

    /**
     * Get all asset categories for dropdown/select.
     */
    public function list()
    {
        $categories = AssetCategory::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
} 