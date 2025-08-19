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
        $basis = $request->query('cost_basis', config('inventory.abc_cost_basis', 'average'));
        $thrA = (float) $request->query('thr_a', config('inventory.abc_thresholds.a', 0.8));
        $thrB = (float) $request->query('thr_b', config('inventory.abc_thresholds.b', 0.95));

        if ($basis === 'unit') {
            // Compute value using parts.unit_cost * total qty on hand
            $rows = DB::table('inventory_stocks')
                ->join('inventory_parts', 'inventory_stocks.part_id', '=', 'inventory_parts.id')
                ->select(
                    'inventory_parts.id as part_id',
                    'inventory_parts.name',
                    DB::raw('SUM(inventory_stocks.on_hand) as qty'),
                    DB::raw('COALESCE(inventory_parts.unit_cost, 0) as unit_cost')
                )
                ->where('inventory_stocks.company_id', $companyId)
                ->groupBy('inventory_parts.id', 'inventory_parts.name', 'inventory_parts.unit_cost')
                ->get()
                ->map(function ($r) {
                    $r->value = (float) $r->qty * (float) $r->unit_cost;
                    return $r;
                })
                ->sortByDesc('value')
                ->values();
        } else {
            // Default: moving average cost basis (stocks.average_cost)
            $rows = DB::table('inventory_stocks')
                ->join('inventory_parts', 'inventory_stocks.part_id', '=', 'inventory_parts.id')
                ->select('inventory_parts.id as part_id', 'inventory_parts.name', DB::raw('SUM(inventory_stocks.on_hand * inventory_stocks.average_cost) as value'))
                ->where('inventory_stocks.company_id', $companyId)
                ->groupBy('inventory_parts.id', 'inventory_parts.name')
                ->orderByDesc('value')
                ->get();
        }

        $total = max(1, $rows->sum('value'));
        $running = 0;
        $result = [];
        foreach ($rows as $row) {
            $running += $row->value;
            $individual = $row->value / $total; // 0..1
            $cumulative = $running / $total;    // 0..1
            $class = $cumulative <= $thrA ? 'A' : ($cumulative <= $thrB ? 'B' : 'C');
            $result[] = [
                'part_id' => $row->part_id,
                'name' => $row->name,
                'value' => round($row->value, 2),
                'individual_percentage' => round($individual * 100, 2),
                'cumulative_percentage' => round($cumulative * 100, 2),
                'cumulative_ratio' => round($cumulative, 4), // backward compatibility
                'class' => $class,
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function abcAnalysisExport(Request $request)
    {
        // Reuse the same calculation; extract data from JSON response
        $response = $this->abcAnalysis($request);
        $payload = method_exists($response, 'getData') ? $response->getData(true) : [];
        $data = $payload['data'] ?? [];
        $filename = 'abc-analysis-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Part ID', 'Name', 'Value', 'Individual %', 'Cumulative %', 'Class']);
            foreach ($data as $row) {
                fputcsv($out, [
                    $row['part_id'] ?? null,
                    $row['name'] ?? null,
                    $row['value'] ?? 0,
                    $row['individual_percentage'] ?? 0,
                    $row['cumulative_percentage'] ?? (($row['cumulative_ratio'] ?? 0) * 100),
                    $row['class'] ?? null,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}


