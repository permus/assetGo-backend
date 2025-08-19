<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{InventoryPart, InventoryStock, InventoryTransaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function overview(Request $request)
    {
        $companyId = $request->user()->company_id;

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

        return response()->json([
            'success' => true,
            'data' => [
                'total_value' => round($totalValue, 2),
                'total_parts' => $totalParts,
                'low_stock_count' => $lowStock,
                'out_of_stock_count' => $outOfStock,
            ]
        ]);
    }
}


