<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\{InventoryStock, InventoryPart, InventoryTransaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * GET /api/inventory/analytics/kpis
     * Returns key KPIs: inventory turnover, avg days on hand, monthly carrying cost, dead stock value.
     * Query params:
     * - period: 1m|3m|6m|1y (for turnover window; annualized result)
     * - carrying_rate: annual carrying cost rate as decimal (e.g. 0.24 for 24%). Default 0.0
     * - dead_days: days threshold for dead stock. Default 90
     */
    public function kpis(Request $request)
    {
        $companyId = $request->user()->company_id;
        $period = $request->query('period', '1y');
        $annualCarryingRate = (float) $request->query('carrying_rate', 0.0);
        $deadDays = (int) $request->query('dead_days', 90);

        $months = match ($period) {
            '1m' => 1,
            '3m' => 3,
            '6m' => 6,
            default => 12,
        };

        $end = now();
        $start = now()->copy()->subMonths($months);

        // Compute COGS proxy within window
        $cogs = (float) \DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['issue', 'transfer_out'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');

        // Ending inventory value
        $endValue = (float) \DB::table('inventory_stocks')
            ->where('company_id', $companyId)
            ->selectRaw('COALESCE(SUM(on_hand * average_cost), 0) as value')
            ->value('value');

        // Approximate start value using movements
        $inValue = (float) \DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['receipt', 'return', 'transfer_in'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');
        $outValue = (float) \DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['issue', 'transfer_out'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');
        $netChange = $inValue - $outValue;
        $startValue = max(0.0, $endValue - $netChange);
        $avgInventoryValue = ($startValue + $endValue) / 2.0;

        // Turnover annualized
        $windowTurnover = ($avgInventoryValue > 0 && $cogs > 0) ? ($cogs / $avgInventoryValue) : 0.0;
        $annualizeFactor = $months > 0 ? (12.0 / $months) : 1.0;
        $turnover = round($windowTurnover * $annualizeFactor, 4);
        $daysOnHand = $turnover > 0 ? round(365.0 / $turnover, 2) : null;

        // Carrying cost: average inventory value * annual rate / 12 (monthly)
        $monthlyCarryingCost = $annualCarryingRate > 0
            ? round(($avgInventoryValue * $annualCarryingRate) / 12.0, 2)
            : 0.0;

        // Dead stock: value of items with last movement older than threshold
        $stockAgg = \DB::table('inventory_stocks')
            ->select('part_id', \DB::raw('SUM(on_hand) as on_hand'), \DB::raw('SUM(on_hand * average_cost) as value'))
            ->where('company_id', $companyId)
            ->groupBy('part_id');
        $lastMovement = \DB::table('inventory_transactions')
            ->select('part_id', \DB::raw('MAX(created_at) as last_movement_at'))
            ->where('company_id', $companyId)
            ->groupBy('part_id');
        $rows = \DB::table('inventory_parts')
            ->joinSub($stockAgg, 's', function ($join) { $join->on('inventory_parts.id', '=', 's.part_id'); })
            ->leftJoinSub($lastMovement, 'lm', function ($join) { $join->on('inventory_parts.id', '=', 'lm.part_id'); })
            ->where('inventory_parts.company_id', $companyId)
            ->where('s.on_hand', '>', 0)
            ->select('inventory_parts.id as part_id', 's.value', 'lm.last_movement_at')
            ->get();

        $now = now();
        $deadValue = 0.0;
        $deadItems = 0;
        foreach ($rows as $r) {
            $days = $r->last_movement_at ? $now->diffInDays(\Carbon\Carbon::parse($r->last_movement_at)) : 99999;
            if ($days >= $deadDays) {
                $deadValue += (float) $r->value;
                $deadItems += 1;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'turnover' => $turnover,
                'days_on_hand' => $daysOnHand,
                'avg_inventory_value' => round($avgInventoryValue, 2),
                'carrying_cost_monthly' => $monthlyCarryingCost,
                'carrying_rate_annual' => round($annualCarryingRate, 4),
                'dead_stock_value' => round($deadValue, 2),
                'dead_stock_items' => $deadItems,
                'dead_days_threshold' => $deadDays,
            ]
        ]);
    }
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

    /**
     * GET /api/inventory/analytics/turnover
     * Calculates inventory turnover and days on hand for a given period.
     */
    public function turnover(Request $request)
    {
        $companyId = $request->user()->company_id;
        $period = $request->query('period', '1y'); // 1m|3m|6m|1y

        $months = match ($period) {
            '1m' => 1,
            '3m' => 3,
            '6m' => 6,
            default => 12,
        };

        $end = now();
        $start = now()->copy()->subMonths($months);

        // COGS proxy: issues and transfer_out within the period
        $cogs = (float) \DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['issue', 'transfer_out'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');

        // Current (end) inventory value
        $endValue = (float) \DB::table('inventory_stocks')
            ->where('company_id', $companyId)
            ->selectRaw('COALESCE(SUM(on_hand * average_cost), 0) as value')
            ->value('value');

        // Approximate start value using net movement value over the period
        $inValue = (float) \DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['receipt', 'return', 'transfer_in'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');

        $outValue = (float) \DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['issue', 'transfer_out'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total')
            ->value('total');

        $netChange = $inValue - $outValue; // end = start + netChange ⇒ start = end - netChange
        $startValue = max(0.0, $endValue - $netChange);

        $avgInventoryValue = ($startValue + $endValue) / 2.0;

        // Turnover for the selected window
        $windowTurnover = ($avgInventoryValue > 0 && $cogs > 0) ? ($cogs / $avgInventoryValue) : 0.0;
        // Annualize to "times per year" to match business definition
        $annualizeFactor = $months > 0 ? (12.0 / $months) : 1.0;
        $turnover = round($windowTurnover * $annualizeFactor, 4);
        $daysOnHand = $turnover > 0 ? round(365.0 / $turnover, 2) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'cogs' => round($cogs, 2),
                'avg_inventory_value' => round($avgInventoryValue, 2),
                'turnover' => $turnover,
                'days_on_hand' => $daysOnHand,
            ]
        ]);
    }

    /**
     * GET /api/inventory/analytics/stock-aging
     * Returns aging buckets and slow moving items (≥ max band days since last movement).
     */
    public function stockAging(Request $request)
    {
        $companyId = $request->user()->company_id;
        $bands = $request->query('bands', [30, 60, 90]);
        if (!is_array($bands)) {
            $bands = [30, 60, 90];
        }
        $bands = collect($bands)
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Aggregate stocks by part
        $stockAgg = \DB::table('inventory_stocks')
            ->select('part_id', \DB::raw('SUM(on_hand) as on_hand'), \DB::raw('SUM(on_hand * average_cost) as value'))
            ->where('company_id', $companyId)
            ->groupBy('part_id');

        // Last movement per part
        $lastMovement = \DB::table('inventory_transactions')
            ->select('part_id', \DB::raw('MAX(created_at) as last_movement_at'))
            ->where('company_id', $companyId)
            ->groupBy('part_id');

        $rows = \DB::table('inventory_parts')
            ->joinSub($stockAgg, 's', function ($join) {
                $join->on('inventory_parts.id', '=', 's.part_id');
            })
            ->leftJoinSub($lastMovement, 'lm', function ($join) {
                $join->on('inventory_parts.id', '=', 'lm.part_id');
            })
            ->where('inventory_parts.company_id', $companyId)
            ->where('s.on_hand', '>', 0)
            ->select('inventory_parts.id as part_id', 'inventory_parts.name', 's.on_hand', 's.value', 'lm.last_movement_at')
            ->get();

        $now = now();
        $items = $rows->map(function ($r) use ($now) {
            $days = null;
            if (!empty($r->last_movement_at)) {
                $days = $now->diffInDays(\Carbon\Carbon::parse($r->last_movement_at));
            } else {
                // No movement ever recorded
                $days = 99999;
            }
            return [
                'part_id' => (int) $r->part_id,
                'name' => $r->name,
                'on_hand' => (float) $r->on_hand,
                'value' => round((float) $r->value, 2),
                'last_movement_at' => $r->last_movement_at,
                'days_since_movement' => $days,
            ];
        });

        // Build buckets
        $bucketDefs = [];
        $from = 0;
        foreach ($bands as $b) {
            $bucketDefs[] = [
                'label' => $from . '-' . $b,
                'days_from' => $from,
                'days_to' => $b,
                'count' => 0,
                'value' => 0.0,
            ];
            $from = $b + 1;
        }
        // 90+ bucket
        $bucketDefs[] = [
            'label' => ($from) . '+',
            'days_from' => $from,
            'days_to' => null,
            'count' => 0,
            'value' => 0.0,
        ];

        // Assign items to buckets
        foreach ($items as $it) {
            $days = (int) ($it['days_since_movement'] ?? 0);
            $assigned = false;
            foreach ($bucketDefs as &$bd) {
                if ($bd['days_to'] === null) {
                    if ($days >= $bd['days_from']) {
                        $bd['count'] += 1;
                        $bd['value'] += $it['value'];
                        $assigned = true;
                        break;
                    }
                } else {
                    if ($days >= $bd['days_from'] && $days <= $bd['days_to']) {
                        $bd['count'] += 1;
                        $bd['value'] += $it['value'];
                        $assigned = true;
                        break;
                    }
                }
            }
            unset($bd);
            if (!$assigned) {
                // Should not happen, but guard
                $bucketDefs[count($bucketDefs) - 1]['count'] += 1;
                $bucketDefs[count($bucketDefs) - 1]['value'] += $it['value'];
            }
        }

        $maxBand = !empty($bands) ? max($bands) : 90;
        $slowMoving = $items->filter(fn($it) => (int) $it['days_since_movement'] >= $maxBand)
            ->values()
            ->all();

        // Round bucket values
        foreach ($bucketDefs as &$bd) {
            $bd['value'] = round($bd['value'], 2);
        }
        unset($bd);

        return response()->json([
            'success' => true,
            'data' => [
                'buckets' => array_values($bucketDefs),
                'slow_moving' => $slowMoving,
            ]
        ]);
    }

    /**
     * GET /api/inventory/analytics/turnover-by-category
     * Returns annualized turnover and related values per part category for a given period.
     * Query: period=1m|3m|6m|1y (default 6m)
     */
    public function turnoverByCategory(Request $request)
    {
        $companyId = $request->user()->company_id;
        $period = $request->query('period', '6m');

        $months = match ($period) {
            '1m' => 1,
            '3m' => 3,
            '6m' => 6,
            default => 12,
        };

        $end = now();
        $start = now()->copy()->subMonths($months);

        // Helper closure to normalize category fields
        $normalize = function($rows) {
            $out = [];
            foreach ($rows as $r) {
                $key = (string)($r->category_id ?? 'null');
                $out[$key] = [
                    'category_id' => $r->category_id,
                    'category_name' => $r->category_name ?? 'Uncategorized',
                    'value' => (float) ($r->value ?? 0),
                ];
            }
            return $out;
        };

        // COGS per category over window
        $cogsRows = \DB::table('inventory_transactions as t')
            ->join('inventory_parts as p', 't.part_id', '=', 'p.id')
            ->leftJoin('inventory_categories as c', 'p.category_id', '=', 'c.id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.type', ['issue', 'transfer_out'])
            ->whereBetween('t.created_at', [$start, $end])
            ->selectRaw("p.category_id as category_id, COALESCE(c.name, 'Uncategorized') as category_name, COALESCE(SUM(ABS(t.quantity) * t.unit_cost), 0) as value")
            ->groupBy('p.category_id', 'c.name')
            ->get();
        $cogsByCat = $normalize($cogsRows);

        // Ending inventory value per category
        $endRows = \DB::table('inventory_stocks as s')
            ->join('inventory_parts as p', 's.part_id', '=', 'p.id')
            ->leftJoin('inventory_categories as c', 'p.category_id', '=', 'c.id')
            ->where('s.company_id', $companyId)
            ->selectRaw("p.category_id as category_id, COALESCE(c.name, 'Uncategorized') as category_name, COALESCE(SUM(s.on_hand * s.average_cost), 0) as value")
            ->groupBy('p.category_id', 'c.name')
            ->get();
        $endByCat = $normalize($endRows);

        // In and Out values (movement value) per category over window
        $inRows = \DB::table('inventory_transactions as t')
            ->join('inventory_parts as p', 't.part_id', '=', 'p.id')
            ->leftJoin('inventory_categories as c', 'p.category_id', '=', 'c.id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.type', ['receipt', 'return', 'transfer_in'])
            ->whereBetween('t.created_at', [$start, $end])
            ->selectRaw("p.category_id as category_id, COALESCE(c.name, 'Uncategorized') as category_name, COALESCE(SUM(ABS(t.quantity) * t.unit_cost), 0) as value")
            ->groupBy('p.category_id', 'c.name')
            ->get();
        $inByCat = $normalize($inRows);

        $outRows = \DB::table('inventory_transactions as t')
            ->join('inventory_parts as p', 't.part_id', '=', 'p.id')
            ->leftJoin('inventory_categories as c', 'p.category_id', '=', 'c.id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.type', ['issue', 'transfer_out'])
            ->whereBetween('t.created_at', [$start, $end])
            ->selectRaw("p.category_id as category_id, COALESCE(c.name, 'Uncategorized') as category_name, COALESCE(SUM(ABS(t.quantity) * t.unit_cost), 0) as value")
            ->groupBy('p.category_id', 'c.name')
            ->get();
        $outByCat = $normalize($outRows);

        // Collect keys
        $keys = collect(array_unique(array_merge(
            array_keys($cogsByCat), array_keys($endByCat), array_keys($inByCat), array_keys($outByCat)
        )));

        $annualize = $months > 0 ? (12.0 / $months) : 1.0;
        $result = [];
        foreach ($keys as $k) {
            $name = $endByCat[$k]['category_name'] ?? ($cogsByCat[$k]['category_name'] ?? 'Uncategorized');
            $endValue = $endByCat[$k]['value'] ?? 0.0;
            $inValue = $inByCat[$k]['value'] ?? 0.0;
            $outValue = $outByCat[$k]['value'] ?? 0.0;
            $cogs = $cogsByCat[$k]['value'] ?? 0.0;

            $startValue = max(0.0, $endValue - ($inValue - $outValue));
            $avgInv = ($startValue + $endValue) / 2.0;
            $windowTurnover = ($avgInv > 0 && $cogs > 0) ? ($cogs / $avgInv) : 0.0;
            $turnover = round($windowTurnover * $annualize, 4);
            $daysOnHand = $turnover > 0 ? round(365.0 / $turnover, 2) : null;

            $result[] = [
                'category_id' => $k === 'null' ? null : (int) $k,
                'category_name' => $name,
                'cogs' => round($cogs, 2),
                'avg_inventory_value' => round($avgInv, 2),
                'turnover' => $turnover,
                'days_on_hand' => $daysOnHand,
            ];
        }

        // Sort descending by turnover
        usort($result, function($a, $b) {
            return ($b['turnover'] <=> $a['turnover']);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'categories' => $result,
            ],
        ]);
    }

    /**
     * GET /api/inventory/analytics/monthly-turnover-trend
     * Returns monthly turnover points (non-annualized) for the selected range.
     */
    public function monthlyTurnoverTrend(Request $request)
    {
        $companyId = $request->user()->company_id;
        $period = $request->query('period', '6m');

        $months = match ($period) {
            '1m' => 1,
            '3m' => 3,
            '6m' => 6,
            default => 12,
        };

        $end = now();
        $start = now()->copy()->subMonths($months - 1)->startOfMonth();

        // Build month keys oldest -> newest
        $monthKeys = [];
        $cursor = $start->copy();
        for ($i = 0; $i < $months; $i++) {
            $monthKeys[] = [
                'ym' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
                'carbon' => $cursor->copy(),
            ];
            $cursor->addMonth();
        }

        // Aggregate outbound (COGS proxy) per month
        $outMap = \DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['issue', 'transfer_out'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Aggregate inbound per month
        $inMap = \DB::table('inventory_transactions')
            ->where('company_id', $companyId)
            ->whereIn('type', ['receipt', 'return', 'transfer_in'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COALESCE(SUM(ABS(quantity) * unit_cost), 0) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Current ending inventory value (for the latest month end)
        $endValueNow = (float) \DB::table('inventory_stocks')
            ->where('company_id', $companyId)
            ->selectRaw('COALESCE(SUM(on_hand * average_cost), 0) as value')
            ->value('value');

        $pointsDesc = []; // newest -> oldest while computing
        $endVal = $endValueNow;
        for ($i = count($monthKeys) - 1; $i >= 0; $i--) {
            $ym = $monthKeys[$i]['ym'];
            $label = $monthKeys[$i]['label'];
            $inValue = (float) ($inMap[$ym] ?? 0);
            $outValue = (float) ($outMap[$ym] ?? 0);
            $netChange = $inValue - $outValue; // end = start + netChange
            $startVal = max(0.0, $endVal - $netChange);
            $avgVal = ($startVal + $endVal) / 2.0;
            $cogs = $outValue; // outbound total for month
            $turnover = ($avgVal > 0 && $cogs > 0) ? ($cogs / $avgVal) : 0.0; // monthly ratio

            $pointsDesc[] = [
                'month' => $ym,
                'label' => $label,
                'cogs' => round($cogs, 2),
                'avg_inventory_value' => round($avgVal, 2),
                'turnover' => round($turnover, 4),
            ];

            // Previous month end becomes this month's start
            $endVal = $startVal;
        }

        // Reverse to oldest -> newest for display
        $points = array_reverse($pointsDesc);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'points' => $points,
            ],
        ]);
    }
}


