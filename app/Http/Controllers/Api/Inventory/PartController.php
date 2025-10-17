<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryPart;
use App\Services\{InventoryAuditService, InventoryCacheService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartController extends Controller
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
}


