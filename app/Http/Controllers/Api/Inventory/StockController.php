<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{InventoryStock, InventoryPart, InventoryLocation, InventoryTransaction};
use App\Services\InventoryService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = InventoryStock::with(['part','location'])->forCompany($companyId);

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('part_id')) {
            $query->where('part_id', $request->part_id);
        }
        if ($search = $request->get('search')) {
            $query->whereHas('part', function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('part_number', 'like', "%$search%");
            });
        }

        $perPage = min($request->get('per_page', 15), 100);
        return response()->json(['success' => true, 'data' => $query->paginate($perPage)]);
    }

    public function adjust(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'part_id' => 'required|integer|exists:inventory_parts,id',
            'location_id' => 'required|integer|exists:inventory_locations,id',
            'type' => 'required|in:receipt,issue,adjustment,return',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string|max:255'
        ]);

        try {
            $txn = $service->adjustStock(
                $request->user()->company_id,
                $data['part_id'],
                $data['location_id'],
                $data['quantity'],
                $data['type'],
                [
                    'unit_cost' => $data['unit_cost'] ?? null,
                    'reason' => $data['reason'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'user_id' => $request->user()->id,
                ]
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $txn]);
    }

    public function transfer(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'part_id' => 'required|integer|exists:inventory_parts,id',
            'from_location_id' => 'required|integer|exists:inventory_locations,id',
            'to_location_id' => 'required|integer|exists:inventory_locations,id|different:from_location_id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string|max:255'
        ]);

        try {
            [$out, $in] = $service->transfer(
                $request->user()->company_id,
                $data['part_id'],
                $data['from_location_id'],
                $data['to_location_id'],
                $data['quantity'],
                [
                    'reason' => $data['reason'] ?? 'transfer',
                    'notes' => $data['notes'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'user_id' => $request->user()->id,
                ]
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => ['transfer_out' => $out, 'transfer_in' => $in]]);
    }
}


