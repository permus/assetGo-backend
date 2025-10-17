<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{InventoryPart, InventoryStock, InventoryTransaction};
use App\Services\InventoryCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $cacheService;

    public function __construct(InventoryCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function overview(Request $request)
    {
        $companyId = $request->user()->company_id;

        return $this->cacheService->getAnalyticsDashboard($companyId, function () use ($companyId) {
            // Total inventory value: sum of on_hand * average_cost across all stocks
            $totalValue = InventoryStock::forCompany($companyId)
            ->select(DB::raw('SUM(on_hand * average_cost) as value'))
            ->value('value') ?? 0;

        // Total unique parts
        $totalParts = InventoryPart::forCompany($companyId)->count();

        // Low stock: total available across all locations for a part <= part.reorder_point (and reorder_point > 0)
        $stockAgg = DB::table('inventory_stocks')
            ->select('part_id', DB::raw('SUM(available) as total_available'))
            ->where('company_id', $companyId)
            ->groupBy('part_id');

        $lowStock = DB::table('inventory_parts')
            ->joinSub($stockAgg, 'agg', function($join) {
                $join->on('inventory_parts.id', '=', 'agg.part_id');
            })
            ->where('inventory_parts.company_id', $companyId)
            ->where('inventory_parts.reorder_point', '>', 0)
            ->whereColumn('agg.total_available', '<=', 'inventory_parts.reorder_point')
            ->count();

        // Out of stock: any stock record with available <= 0
        $outOfStock = DB::table('inventory_stocks')
            ->where('company_id', $companyId)
            ->where('available', '<=', 0)
            ->count();

        // Average Turnover (annualized) for last 6 months window
        $end = now();
        $start = now()->copy()->subMonths(6);
        $cogs = (float) DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['issue', 'transfer_out'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');

        $endValue = (float) DB::table('inventory_stocks')
            ->where('company_id', $companyId)
            ->selectRaw('COALESCE(SUM(on_hand * average_cost), 0) as value')
            ->value('value');

        $inValue = (float) DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['receipt', 'return', 'transfer_in'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');
        $outValue = (float) DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['issue', 'transfer_out'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');
        $netChange = $inValue - $outValue;
        $startValue = max(0.0, $endValue - $netChange);
        $avgInv = ($startValue + $endValue) / 2.0;
        $windowTurnover = ($avgInv > 0 && $cogs > 0) ? ($cogs / $avgInv) : 0.0;
        $avgTurnover = $windowTurnover * 2.0; // annualize 6 months → x2

        // Slow moving items count (>= 90 days since last movement)
        $slowThreshold = 90;
        $lastMovement = DB::table('inventory_transactions')
            ->select('part_id', DB::raw('MAX(created_at) as last_movement_at'))
            ->where('company_id', $companyId)
            ->groupBy('part_id');

        $slowCount = DB::table('inventory_parts')
            ->join('inventory_stocks', 'inventory_parts.id', '=', 'inventory_stocks.part_id')
            ->leftJoinSub($lastMovement, 'lm', function ($join) {
                $join->on('inventory_parts.id', '=', 'lm.part_id');
            })
            ->where('inventory_parts.company_id', $companyId)
            ->where('inventory_stocks.on_hand', '>', 0)
            ->where(function ($q) use ($slowThreshold) {
                $q->whereNull('lm.last_movement_at')
                  ->orWhere('lm.last_movement_at', '<=', now()->subDays($slowThreshold));
            })
            ->distinct('inventory_parts.id')
            ->count('inventory_parts.id');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_value' => round($totalValue, 2),
                    'total_parts' => $totalParts,
                    'low_stock_count' => $lowStock,
                    'out_of_stock_count' => $outOfStock,
                    // New fields
                    'average_turnover' => round($avgTurnover, 4), // times per year
                    'slow_moving_count' => $slowCount,
                ]
            ]);
        });
    }
}


