<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = InventoryCategory::forCompany($companyId)->orderBy('name');
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:inventory_categories,id'
        ]);
        $data['company_id'] = $request->user()->company_id;
        $category = InventoryCategory::create($data);
        return response()->json(['success' => true, 'data' => $category], 201);
    }

    public function update(Request $request, InventoryCategory $category)
    {
        if ($category->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:inventory_categories,id',
            'is_active' => 'nullable|boolean'
        ]);
        $category->update($data);
        return response()->json(['success' => true, 'data' => $category]);
    }

    public function destroy(Request $request, InventoryCategory $category)
    {
        if ($category->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $category->delete();
        return response()->json(['success' => true]);
    }
}


