<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $q = Supplier::forCompany($request->user()->company_id);
        if ($search = $request->get('search')) {
            $q->where('name', 'like', "%$search%");
        }
        return response()->json(['success' => true, 'data' => $q->orderBy('name')->paginate(min($request->get('per_page', 15), 100))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'terms' => 'nullable|string',
        ]);
        $data['company_id'] = $request->user()->company_id;
        $supplier = Supplier::create($data);
        return response()->json(['success' => true, 'data' => $supplier], 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        if ($supplier->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'terms' => 'nullable|string',
        ]);
        $supplier->update($data);
        return response()->json(['success' => true, 'data' => $supplier]);
    }
}


