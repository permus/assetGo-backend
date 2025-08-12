<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryAlert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = InventoryAlert::forCompany($companyId);
        if ($request->filled('is_resolved')) {
            $query->where('is_resolved', filter_var($request->get('is_resolved'), FILTER_VALIDATE_BOOLEAN));
        }
        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(min($request->get('per_page', 15), 100))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'part_id' => 'nullable|integer|exists:inventory_parts,id',
            'alert_type' => 'required|string|max:100',
            'alert_level' => 'required|string|max:50',
            'message' => 'required|string',
        ]);
        $data['company_id'] = $request->user()->company_id;
        $alert = InventoryAlert::create($data);
        return response()->json(['success' => true, 'data' => $alert], 201);
    }

    public function resolve(Request $request, InventoryAlert $alert)
    {
        if ($alert->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $alert->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);
        return response()->json(['success' => true, 'data' => $alert]);
    }
}


