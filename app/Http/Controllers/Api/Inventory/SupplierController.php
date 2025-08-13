<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $q = Supplier::forCompany($request->user()->company_id);
        
        // Search functionality
        if ($search = $request->get('search')) {
            $q->where(function($query) use ($search) {
                $query->where('supplier_code', 'like', "%$search%")
                      ->orWhere('name', 'like', "%$search%")
                      ->orWhere('contact_person', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%");
            });
        }

        // Filter by status or other criteria
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return response()->json([
            'success' => true, 
            'data' => $q->orderBy('name')->paginate(min($request->get('per_page', 15), 100))
        ]);
    }

    public function show(Request $request, Supplier $supplier)
    {
        if ($supplier->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Supplier not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $supplier->load('purchaseOrders')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Required fields
            'name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            
            // Optional fields
            'supplier_code' => 'nullable|string|max:50|unique:suppliers,supplier_code',
            'tax_registration_number' => 'nullable|string|max:100',
            'alternate_phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string',
            'terms' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'credit_limit' => 'nullable|numeric|min:0',
            'delivery_lead_time' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        // Generate supplier code if not provided
        if (empty($data['supplier_code'])) {
            $data['supplier_code'] = 'SUP-' . strtoupper(Str::random(8));
        }

        $data['company_id'] = $request->user()->company_id;
        
        $supplier = Supplier::create($data);
        
        return response()->json(['success' => true, 'data' => $supplier], 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        if ($supplier->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Supplier not found'], 404);
        }

        $data = $request->validate([
            // Required fields
            'name' => 'sometimes|required|string|max:255',
            'contact_person' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'sometimes|required|string|max:50',
            
            // Optional fields
            'supplier_code' => 'nullable|string|max:50|unique:suppliers,supplier_code,' . $supplier->id,
            'tax_registration_number' => 'nullable|string|max:100',
            'alternate_phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'street_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'payment_terms' => 'nullable|string',
            'terms' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'credit_limit' => 'nullable|numeric|min:0',
            'delivery_lead_time' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $supplier->update($data);
        
        return response()->json(['success' => true, 'data' => $supplier]);
    }

    public function destroy(Request $request, Supplier $supplier)
    {
        if ($supplier->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Supplier not found'], 404);
        }

        // Check if supplier has associated purchase orders
        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'success' => false, 
                'message' => 'Cannot delete supplier with associated purchase orders'
            ], 422);
        }

        $supplier->delete();
        
        return response()->json(['success' => true, 'message' => 'Supplier deleted successfully']);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'supplier_ids' => 'required|array',
            'supplier_ids.*' => 'integer|exists:suppliers,id'
        ]);

        $companyId = $request->user()->company_id;
        $suppliers = Supplier::whereIn('id', $request->supplier_ids)
                           ->where('company_id', $companyId)
                           ->get();

        $deletedCount = 0;
        foreach ($suppliers as $supplier) {
            if (!$supplier->purchaseOrders()->exists()) {
                $supplier->delete();
                $deletedCount++;
            }
        }

        return response()->json([
            'success' => true, 
            'message' => "Successfully deleted $deletedCount suppliers",
            'deleted_count' => $deletedCount
        ]);
    }
}


