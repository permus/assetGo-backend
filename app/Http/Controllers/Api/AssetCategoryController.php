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
        $categories = AssetCategory::orderBy('name')->get();
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories
            ]
        ]);
    }
} 