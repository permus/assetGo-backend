<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryPart;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Traits\HasPermissions;
use App\Services\{InventoryAuditService, InventoryCacheService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartController extends Controller
{
    use HasPermissions;

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

        return $this->cacheService->getPartsOverview($companyId, function () use ($companyId) {
            $totalParts = \App\Models\InventoryPart::forCompany($companyId)->count();

            // Low stock: sum available across all locations <= part.reorder_point (and reorder_point > 0)
            $stockAgg = DB::table('inventory_stocks')
                ->select('part_id', DB::raw('SUM(available) as total_available'))
                ->where('company_id', $companyId)
                ->groupBy('part_id');

            $lowStock = DB::table('inventory_parts')
                ->leftJoinSub($stockAgg, 'agg', function($join) {
                    $join->on('inventory_parts.id', '=', 'agg.part_id');
                })
                ->where('inventory_parts.company_id', $companyId)
                ->where('inventory_parts.reorder_point', '>', 0)
                ->whereRaw('COALESCE(agg.total_available, 0) <= inventory_parts.reorder_point')
                ->count();

            // Total value: on_hand * average_cost across all stocks
            $totalValue = DB::table('inventory_stocks')
                ->where('company_id', $companyId)
                ->select(DB::raw('SUM(on_hand * average_cost) as value'))
                ->value('value') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_parts' => $totalParts,
                    'low_stock_count' => $lowStock,
                    'total_value' => round((float)$totalValue, 2),
                ]
            ]);
        });
    }
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

        // Filter archived parts by default unless explicitly requested
        $includeArchived = $request->boolean('include_archived', false);
        if (!$includeArchived) {
            $query->where('status', '!=', 'archived');
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

        // Log the creation
        $this->auditService->logPartCreated(
            $part->id,
            $part->part_number,
            $part->name,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

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
        $originalData = $part->getOriginal();
        $part->update(array_merge($request->only(['description','category_id','reorder_point','reorder_qty','barcode','status','abc_class']), $data));

        // Log the update
        $this->auditService->logPartUpdated(
            $part->id,
            $part->part_number,
            $part->name,
            $part->getChanges(),
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        return response()->json(['success' => true, 'data' => $part]);
    }

    public function destroy(Request $request, InventoryPart $part)
    {
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Store part data before deletion
        $partId = $part->id;
        $partNumber = $part->part_number;
        $partName = $part->name;

        $part->delete();

        // Log the deletion
        $this->auditService->logPartDeleted(
            $partId,
            $partNumber,
            $partName,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        return response()->json(['success' => true]);
    }

    public function archive(Request $request, InventoryPart $part)
    {
        // Check company ownership
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Check permission
        if ($denied = $this->requirePermission('inventory', 'parts_archive')) {
            return $denied;
        }

        // Check if already archived
        if ($part->status === 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Part is already archived'
            ], 422);
        }

        // Validate request
        $data = $request->validate([
            'force' => 'sometimes|boolean',
        ]);

        $force = $data['force'] ?? false;

        // Check for open purchase orders with this part
        $openPOStatuses = ['draft', 'pending', 'ordered', 'approved'];
        $affectedPOs = PurchaseOrderItem::where('part_id', $part->id)
            ->whereHas('purchaseOrder', function ($query) use ($openPOStatuses, $request) {
                $query->whereIn('status', $openPOStatuses)
                      ->where('company_id', $request->user()->company_id);
            })
            ->with('purchaseOrder:id,po_number,status')
            ->get();

        // If there are open POs and force is not true, return warning
        if ($affectedPOs->isNotEmpty() && !$force) {
            $poDetails = $affectedPOs->map(function ($item) {
                return [
                    'po_id' => $item->purchase_order_id,
                    'po_number' => $item->purchaseOrder->po_number ?? 'N/A',
                    'status' => $item->purchaseOrder->status ?? 'N/A',
                    'ordered_qty' => $item->ordered_qty,
                    'received_qty' => $item->received_qty,
                ];
            })->toArray();

            return response()->json([
                'success' => false,
                'message' => 'This part is linked to open purchase orders. Set force=true to archive anyway.',
                'affected_purchase_orders' => $poDetails,
                'requires_force' => true,
            ], 422);
        }

        // Archive the part
        $part->status = 'archived';
        $part->save();

        // Prepare affected PO data for logging
        $affectedPOsLog = $affectedPOs->map(function ($item) {
            return [
                'po_id' => $item->purchase_order_id,
                'po_number' => $item->purchaseOrder->po_number ?? 'N/A',
                'status' => $item->purchaseOrder->status ?? 'N/A',
            ];
        })->toArray();

        // Log the archive action
        $this->auditService->logPartArchived(
            $part->id,
            $part->part_number,
            $part->name,
            $affectedPOsLog,
            $force,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        return response()->json([
            'success' => true,
            'message' => 'Part archived successfully',
            'data' => $part,
            'affected_purchase_orders' => $affectedPOsLog,
        ]);
    }

    public function restore(Request $request, InventoryPart $part)
    {
        // Check company ownership
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Check permission
        if ($denied = $this->requirePermission('inventory', 'parts_restore')) {
            return $denied;
        }

        // Check if part is archived
        if ($part->status !== 'archived') {
            return response()->json([
                'success' => false,
                'message' => 'Part is not archived'
            ], 422);
        }

        // Restore the part
        $part->status = 'active';
        $part->save();

        // Log the restore action
        $this->auditService->logPartRestored(
            $part->id,
            $part->part_number,
            $part->name,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        return response()->json([
            'success' => true,
            'message' => 'Part restored successfully',
            'data' => $part,
        ]);
    }
}


