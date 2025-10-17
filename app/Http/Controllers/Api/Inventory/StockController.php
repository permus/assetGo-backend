<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{InventoryStock, InventoryPart, Location, InventoryTransaction};
use App\Services\{InventoryService, InventoryAuditService, InventoryCacheService};
use Illuminate\Http\Request;
use InvalidArgumentException;

class StockController extends Controller
{
    protected $auditService;
    protected $cacheService;

    public function __construct(InventoryAuditService $auditService, InventoryCacheService $cacheService)
    {
        $this->auditService = $auditService;
        $this->cacheService = $cacheService;
    }
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
            'location_id' => 'required|integer|exists:locations,id',
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

            // Log the adjustment
            $part = InventoryPart::find($data['part_id']);
            $this->auditService->logStockAdjustment(
                $txn->id,
                $data['part_id'],
                $part->name,
                $data['location_id'],
                $data['type'],
                $data['quantity'],
                $request->user()->id,
                $request->user()->email,
                $request->user()->company_id,
                $request->ip()
            );

            // Clear cache
            $this->cacheService->clearStockCache($request->user()->company_id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $txn]);
    }

    public function transfer(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'part_id' => 'required|integer|exists:inventory_parts,id',
            'from_location_id' => 'required|integer|exists:locations,id',
            'to_location_id' => 'required|integer|exists:locations,id|different:from_location_id',
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'nullable|numeric|min:0',
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
                    'unit_cost' => $data['unit_cost'] ?? null,
                    'user_id' => $request->user()->id,
                ]
            );

            // Log the transfer
            $part = InventoryPart::find($data['part_id']);
            $this->auditService->logStockTransfer(
                $data['part_id'],
                $part->name,
                $data['from_location_id'],
                $data['to_location_id'],
                $data['quantity'],
                $request->user()->id,
                $request->user()->email,
                $request->user()->company_id,
                $request->ip()
            );

            // Clear cache
            $this->cacheService->clearStockCache($request->user()->company_id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => ['transfer_out' => $out, 'transfer_in' => $in]]);
    }

    public function reserve(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'part_id' => 'required|integer|exists:inventory_parts,id',
            'location_id' => 'required|integer|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:255'
        ]);
        $stock = $service->reserveStock($request->user()->company_id, $data['part_id'], $data['location_id'], $data['quantity'], [
            'reference' => $data['reference'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        // Log the reservation
        $part = InventoryPart::find($data['part_id']);
        $this->auditService->logStockReservation(
            $data['part_id'],
            $part->name,
            $data['location_id'],
            $data['quantity'],
            'reserve',
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearStockCache($request->user()->company_id);

        return response()->json(['success' => true, 'data' => $stock]);
    }

    public function release(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'part_id' => 'required|integer|exists:inventory_parts,id',
            'location_id' => 'required|integer|exists:locations,id',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:255'
        ]);
        $stock = $service->releaseReservedStock($request->user()->company_id, $data['part_id'], $data['location_id'], $data['quantity'], [
            'reference' => $data['reference'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        // Log the release
        $part = InventoryPart::find($data['part_id']);
        $this->auditService->logStockReservation(
            $data['part_id'],
            $part->name,
            $data['location_id'],
            $data['quantity'],
            'release',
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearStockCache($request->user()->company_id);

        return response()->json(['success' => true, 'data' => $stock]);
    }

    public function count(Request $request, InventoryService $service)
    {
        $data = $request->validate([
            'part_id' => 'required|integer|exists:inventory_parts,id',
            'location_id' => 'required|integer|exists:locations,id',
            'counted_quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string'
        ]);
        $result = $service->performStockCount($request->user()->company_id, $data['part_id'], $data['location_id'], $data['counted_quantity'], [
            'notes' => $data['notes'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        // Log the physical count
        $part = InventoryPart::find($data['part_id']);
        $this->auditService->logStockCount(
            $data['part_id'],
            $part->name,
            $data['location_id'],
            $data['counted_quantity'],
            $result['adjustment'],
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearStockCache($request->user()->company_id);

        return response()->json(['success' => true, 'data' => $result]);
    }
}


