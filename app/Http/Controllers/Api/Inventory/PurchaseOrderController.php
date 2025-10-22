<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{PurchaseOrder, PurchaseOrderItem, InventoryPart, Supplier};
use App\Services\{InventoryService, InventoryAuditService, InventoryCacheService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    protected $auditService;
    protected $cacheService;

    public function __construct(InventoryAuditService $auditService, InventoryCacheService $cacheService)
    {
        $this->auditService = $auditService;
        $this->cacheService = $cacheService;
    }

    public function overview(Request $request)
    {
        $companyId = $request->user()->company_id;

        return $this->cacheService->getPurchaseOrderOverview($companyId, function () use ($companyId) {
            $statusCounts = DB::table('purchase_orders')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->where('company_id', $companyId)
                ->groupBy('status')
                ->pluck('count', 'status');

            $totalPOs = DB::table('purchase_orders')
                ->where('company_id', $companyId)
                ->count();

            $totalValue = DB::table('purchase_orders')
                ->where('company_id', $companyId)
                ->select(DB::raw('SUM(total) as v'))
                ->value('v') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_pos' => $totalPOs,
                    'draft' => (int)($statusCounts['draft'] ?? 0),
                    'pending' => (int)($statusCounts['pending'] ?? 0),
                    'approved' => (int)($statusCounts['approved'] ?? 0),
                    'ordered' => (int)($statusCounts['ordered'] ?? 0),
                    'received' => (int)($statusCounts['received'] ?? 0),
                    'closed' => (int)($statusCounts['closed'] ?? 0),
                    'rejected' => (int)($statusCounts['rejected'] ?? 0),
                    'cancelled' => (int)($statusCounts['cancelled'] ?? 0),
                    'total_value' => round((float)$totalValue, 2),
                ]
            ]);
        });
    }
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
            // Vendor Information
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'vendor_name' => 'required|string|max:255',
            'vendor_contact' => 'required|string|max:255',
            
            // Order Details
            'order_date' => 'required|date',
            'expected_date' => 'required|date',
            
            // Line Items
            'items' => 'required|array|min:1',
            'items.*.part_id' => 'nullable|integer|exists:inventory_parts,id',
            'items.*.part_number' => 'required|string|max:255',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            
            // Order Summary
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            
            // Additional Information
            'terms' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $companyId = $request->user()->company_id;

        // Validate that parts are not archived
        $partIds = collect($data['items'])->pluck('part_id')->filter()->unique();
        if ($partIds->isNotEmpty()) {
            $archivedParts = InventoryPart::whereIn('id', $partIds)
                ->where('status', 'archived')
                ->where('company_id', $companyId)
                ->get(['id', 'part_number', 'name']);

            if ($archivedParts->isNotEmpty()) {
                $archivedList = $archivedParts->map(function ($part) {
                    return $part->part_number . ' - ' . $part->name;
                })->implode(', ');

                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create purchase order with archived parts: ' . $archivedList
                ], 422);
            }
        }

        // Calculate totals
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $lineTotal = $item['unit_cost'] * $item['qty'];
            $subtotal += $lineTotal;
        }

        // Calculate tax if tax_rate is provided, otherwise use tax_amount
        $taxAmount = 0;
        if (isset($data['tax_rate']) && $data['tax_rate'] > 0) {
            $taxAmount = ($subtotal * $data['tax_rate']) / 100;
        } elseif (isset($data['tax_amount'])) {
            $taxAmount = $data['tax_amount'];
        }

        $total = $subtotal + $taxAmount;

        $po = PurchaseOrder::create([
            'company_id' => $companyId,
            'po_number' => 'PO-'.strtoupper(Str::random(8)),
            'supplier_id' => $data['supplier_id'] ?? null,
            'vendor_name' => $data['vendor_name'],
            'vendor_contact' => $data['vendor_contact'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'],
            'status' => 'draft',
            'subtotal' => $subtotal,
            'tax' => $taxAmount,
            'total' => $total,
            'terms' => $data['terms'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // Create line items
        foreach ($data['items'] as $item) {
            $lineTotal = $item['unit_cost'] * $item['qty'];
            PurchaseOrderItem::create([
                'company_id' => $companyId,
                'purchase_order_id' => $po->id,
                'part_id' => $item['part_id'] ?? null,
                'part_number' => $item['part_number'],
                'description' => $item['description'],
                'ordered_qty' => $item['qty'],
                'unit_cost' => $item['unit_cost'],
                'line_total' => $lineTotal,
                'notes' => $data['notes'] ?? null,
            ]);
        }

        // Log the creation
        $this->auditService->logPurchaseOrderCreated(
            $po->id,
            $po->po_number,
            $data['vendor_name'],
            $total,
            $request->user()->id,
            $request->user()->email,
            $companyId,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPurchaseOrderCache($companyId);

        return response()->json(['success' => true, 'data' => $po->load('items','supplier')], 201);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        $data = $request->validate([
            'status' => 'sometimes|required|string|in:draft,pending,approved,ordered,received,closed,rejected,cancelled',
            'expected_date' => 'sometimes|date',
            'vendor_name' => 'sometimes|string|max:255',
            'vendor_contact' => 'sometimes|string|max:255',
            'terms' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
        ]);
        
        $changes = [];
        foreach ($data as $key => $value) {
            if ($purchaseOrder->$key != $value) {
                $changes[$key] = ['old' => $purchaseOrder->$key, 'new' => $value];
            }
        }
        
        $purchaseOrder->update($data);

        // Log the update
        if (!empty($changes)) {
            $this->auditService->logPurchaseOrderUpdated(
                $purchaseOrder->id,
                $purchaseOrder->po_number,
                $changes,
                $request->user()->id,
                $request->user()->email,
                $request->user()->company_id,
                $request->ip()
            );
        }

        // Clear cache
        $this->cacheService->clearPurchaseOrderCache($request->user()->company_id);

        return response()->json(['success' => true, 'data' => $purchaseOrder->fresh(['supplier','items'])]);
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
            'location_id' => 'required|integer|exists:locations,id',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $companyId = $request->user()->company_id;
        $allReceived = true;

        foreach ($data['items'] as $line) {
            $item = PurchaseOrderItem::where('id', $line['item_id'])->where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
            $remaining = $item->ordered_qty - $item->received_qty;
            $qty = min($remaining, (int)$line['receive_qty']);

            // Resolve part_id if missing using part_number within the same company
            $partId = $item->part_id;
            if (!$partId) {
                $part = InventoryPart::where('company_id', $companyId)
                    ->where('part_number', $item->part_number)
                    ->first();
                if ($part) {
                    $partId = $part->id;
                    // Persist linkage for future operations
                    $item->part_id = $partId;
                    $item->save();
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => "PO item {$item->id} is not linked to a part and no part was found by part_number '{$item->part_number}'. Link a part or create it before receiving."
                    ], 422);
                }
            }

            if ($qty > 0) {
                $service->adjustStock($companyId, (int)$partId, (int)$data['location_id'], $qty, 'receipt', [
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

        // Log the receiving
        $this->auditService->logPurchaseOrderReceived(
            $purchaseOrder->id,
            $purchaseOrder->po_number,
            $data['items'],
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear caches (both PO and stock)
        $this->cacheService->clearPurchaseOrderCache($request->user()->company_id);
        $this->cacheService->clearStockCache($request->user()->company_id);

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
        
        $oldStatus = $po->status;
        
        if ($data['approve']) {
            $po->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $newStatus = 'approved';
        } else {
            $po->update(['status' => 'rejected', 'reject_comment' => $data['comment'] ?? null]);
            $newStatus = 'rejected';
        }

        // Log the approval/rejection
        $this->auditService->logPurchaseOrderApproved(
            $po->id,
            $po->po_number,
            $oldStatus,
            $newStatus,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPurchaseOrderCache($request->user()->company_id);

        return response()->json(['success' => true, 'data' => $po]);
    }
}


