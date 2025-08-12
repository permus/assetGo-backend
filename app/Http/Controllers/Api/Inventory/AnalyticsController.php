<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{InventoryStock, InventoryPart, InventoryTransaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request)
    {
        $companyId = $request->user()->company_id;

        $totalValue = InventoryStock::forCompany($companyId)
            ->select(DB::raw('SUM(on_hand * average_cost) as value'))
            ->value('value') ?? 0;

        $totalParts = InventoryPart::forCompany($companyId)->count();

        // Low stock: sum available across all locations <= part.reorder_point
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

    public function abcAnalysis(Request $request)
    {
        $companyId = $request->user()->company_id;
        // Simple ABC by value contribution
        $rows = DB::table('inventory_stocks')
            ->join('inventory_parts', 'inventory_stocks.part_id', '=', 'inventory_parts.id')
            ->select('inventory_parts.id as part_id', 'inventory_parts.name', DB::raw('SUM(inventory_stocks.on_hand * inventory_stocks.average_cost) as value'))
            ->where('inventory_stocks.company_id', $companyId)
            ->groupBy('inventory_parts.id', 'inventory_parts.name')
            ->orderByDesc('value')
            ->get();

        $total = max(1, $rows->sum('value'));
        $running = 0;
        $result = [];
        foreach ($rows as $row) {
            $running += $row->value;
            $contrib = $running / $total;
            $class = $contrib <= 0.8 ? 'A' : ($contrib <= 0.95 ? 'B' : 'C');
            $result[] = [
                'part_id' => $row->part_id,
                'name' => $row->name,
                'value' => round($row->value, 2),
                'cumulative_ratio' => round($contrib, 4),
                'class' => $class,
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}


