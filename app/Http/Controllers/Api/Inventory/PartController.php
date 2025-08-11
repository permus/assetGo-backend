<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryPart;
use Illuminate\Http\Request;

class PartController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = InventoryPart::forCompany($companyId);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('part_number', 'like', "%$search%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min($request->get('per_page', 15), 100);
        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->paginate($perPage)
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'part_number' => 'required|string|max:255|unique:inventory_parts,part_number',
            'uom' => 'required|string|max:50',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $data = $request->only(['name','part_number','description','uom','unit_cost','category_id','reorder_point','reorder_qty','barcode']);
        $data['company_id'] = $request->user()->company_id;
        $data['user_id'] = $request->user()->id;
        $part = InventoryPart::create($data);
        return response()->json(['success' => true, 'data' => $part], 201);
    }

    public function show(Request $request, InventoryPart $part)
    {
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $part]);
    }

    public function update(Request $request, InventoryPart $part)
    {
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'uom' => 'sometimes|required|string|max:50',
            'unit_cost' => 'nullable|numeric|min:0',
        ];
        if ($request->filled('part_number') && $request->part_number !== $part->part_number) {
            $rules['part_number'] = 'string|max:255|unique:inventory_parts,part_number';
        }
        $data = $request->validate($rules);
        $part->update(array_merge($request->only(['description','category_id','reorder_point','reorder_qty','barcode','status','abc_class']), $data));
        return response()->json(['success' => true, 'data' => $part]);
    }

    public function destroy(Request $request, InventoryPart $part)
    {
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $part->delete();
        return response()->json(['success' => true]);
    }
}


