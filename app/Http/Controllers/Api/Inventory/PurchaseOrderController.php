<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{PurchaseOrder, PurchaseOrderItem, InventoryPart, Supplier};
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = PurchaseOrder::with(['supplier','items'])->forCompany($companyId);
        if ($request->filled('status')) $query->where('status', $request->status);
        $per = min($request->get('per_page', 15), 100);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('id')->paginate($per)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'order_date' => 'nullable|date',
            'expected_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'required|integer|exists:inventory_parts,id',
            'items.*.ordered_qty' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0'
        ]);

        $companyId = $request->user()->company_id;

        $po = PurchaseOrder::create([
            'company_id' => $companyId,
            'po_number' => 'PO-'.strtoupper(Str::random(8)),
            'supplier_id' => $data['supplier_id'],
            'order_date' => $data['order_date'] ?? now(),
            'expected_date' => $data['expected_date'] ?? null,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);

        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $lineTotal = $item['unit_cost'] * $item['ordered_qty'];
            $subtotal += $lineTotal;
            PurchaseOrderItem::create([
                'company_id' => $companyId,
                'purchase_order_id' => $po->id,
                'part_id' => $item['part_id'],
                'ordered_qty' => $item['ordered_qty'],
                'unit_cost' => $item['unit_cost'],
                'line_total' => $lineTotal,
            ]);
        }
        $po->update(['subtotal' => $subtotal, 'total' => $subtotal]);

        return response()->json(['success' => true, 'data' => $po->load('items','supplier')], 201);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, InventoryService $service)
    {
        if ($purchaseOrder->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:purchase_order_items,id',
            'items.*.receive_qty' => 'required|integer|min:0',
            'location_id' => 'required|integer|exists:inventory_locations,id',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $companyId = $request->user()->company_id;
        $allReceived = true;

        foreach ($data['items'] as $line) {
            $item = PurchaseOrderItem::where('id', $line['item_id'])->where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
            $remaining = $item->ordered_qty - $item->received_qty;
            $qty = min($remaining, (int)$line['receive_qty']);
            if ($qty > 0) {
                $service->adjustStock($companyId, $item->part_id, $data['location_id'], $qty, 'receipt', [
                    'unit_cost' => $item->unit_cost,
                    'reason' => 'PO Receipt',
                    'reference' => $purchaseOrder->po_number,
                    'notes' => $data['notes'] ?? null,
                    'user_id' => $request->user()->id,
                    'related_id' => $purchaseOrder->id,
                ]);
                $item->increment('received_qty', $qty);
            }
            if ($item->ordered_qty > $item->received_qty) {
                $allReceived = false;
            }
        }

        if ($allReceived) {
            // Keep within current ENUM definition
            $purchaseOrder->update(['status' => 'closed']);
        } else {
            $purchaseOrder->update(['status' => 'ordered']);
        }

        return response()->json(['success' => true, 'data' => $purchaseOrder->fresh('items')]);
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id' => 'required|integer|exists:purchase_orders,id',
            'approve' => 'required|boolean',
            'comment' => 'nullable|string'
        ]);
        $po = PurchaseOrder::findOrFail($data['purchase_order_id']);
        if ($po->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        if ($data['approve']) {
            $po->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        } else {
            $po->update(['status' => 'rejected', 'reject_comment' => $data['comment'] ?? null]);
        }
        return response()->json(['success' => true, 'data' => $po]);
    }
}


