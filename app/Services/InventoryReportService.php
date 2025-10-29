<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InventoryReportService extends ReportService
{
    /**
     * Generate report data for inventory module
     */
    public function generateReport(string $reportKey, array $params = []): array
    {
        return match($reportKey) {
            'inventory.current.stock' => $this->generateCurrentStock($params),
            'inventory.abc.analysis' => $this->generateAbcAnalysis($params),
            'inventory.slow_moving' => $this->generateSlowMoving($params),
            'inventory.reorder_analysis' => $this->generateReorderAnalysis($params),
            default => throw new \InvalidArgumentException("Unknown report key: {$reportKey}")
        };
    }

    /**
     * Available inventory reports
     */
    public function getAvailableReports(): array
    {
        return [
            'inventory.current.stock' => [
                'key' => 'inventory.current.stock',
                'name' => 'Current Stock Levels',
            ],
            'inventory.abc.analysis' => [
                'key' => 'inventory.abc.analysis',
                'name' => 'ABC Analysis',
            ],
            'inventory.slow_moving' => [
                'key' => 'inventory.slow_moving',
                'name' => 'Slow Moving Inventory',
            ],
            'inventory.reorder_analysis' => [
                'key' => 'inventory.reorder_analysis',
                'name' => 'Reorder Analysis',
            ],
        ];
    }

    /**
     * ABC Analysis (value-based) using inventory_stocks and inventory_parts
     */
    private function generateAbcAnalysis(array $params = []): array
    {
        return $this->trackPerformance('inventory.abc.analysis', function () use ($params) {
            $companyId = $this->getCompanyId();

            $basis = $params['cost_basis'] ?? config('inventory.abc_cost_basis', 'average'); // 'average' | 'unit'
            $thrA = (float) ($params['thr_a'] ?? config('inventory.abc_thresholds.a', 0.8));
            $thrB = (float) ($params['thr_b'] ?? config('inventory.abc_thresholds.b', 0.95));

            if ($basis === 'unit') {
                // Unit cost basis: parts.unit_cost * total on hand
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
                // Moving average cost basis: on_hand * average_cost
                $rows = DB::table('inventory_stocks')
                    ->join('inventory_parts', 'inventory_stocks.part_id', '=', 'inventory_parts.id')
                    ->select(
                        'inventory_parts.id as part_id',
                        'inventory_parts.name',
                        DB::raw('SUM(inventory_stocks.on_hand * inventory_stocks.average_cost) as value')
                    )
                    ->where('inventory_stocks.company_id', $companyId)
                    ->groupBy('inventory_parts.id', 'inventory_parts.name')
                    ->orderByDesc('value')
                    ->get();
            }

            $totalValue = max(1.0, (float) $rows->sum('value'));
            $running = 0.0;
            $classified = [];
            $counts = ['A' => 0, 'B' => 0, 'C' => 0];

            foreach ($rows as $row) {
                $running += (float) $row->value;
                $cum = $running / $totalValue;
                $class = $cum <= $thrA ? 'A' : ($cum <= $thrB ? 'B' : 'C');
                $counts[$class] += 1;
                $classified[] = [
                    'part_id' => $row->part_id,
                    'name' => $row->name,
                    'value' => round((float) $row->value, 2),
                    'cumulative' => round($cum, 4),
                    'class' => $class,
                ];
            }

            $summary = [
                'total_value' => round($totalValue, 2),
                'count_a' => $counts['A'],
                'count_b' => $counts['B'],
                'count_c' => $counts['C'],
                'threshold_a' => $thrA,
                'threshold_b' => $thrB,
                'basis' => $basis,
            ];

            return $this->formatResponse([
                'summary' => $summary,
                'items' => $classified,
            ]);
        });
    }

    /**
     * Generate current stock levels report
     */
    private function generateCurrentStock(array $params = []): array
    {
        return $this->trackPerformance('inventory.current.stock', function () use ($params) {
            $companyId = $this->getCompanyId();

            $query = DB::table('inventory_stocks')
                ->join('inventory_parts', 'inventory_stocks.part_id', '=', 'inventory_parts.id')
                ->leftJoin('inventory_locations', 'inventory_stocks.location_id', '=', 'inventory_locations.id')
                ->select(
                    'inventory_parts.id as part_id',
                    'inventory_parts.name as part_name',
                    'inventory_parts.part_number',
                    'inventory_parts.unit_cost',
                    'inventory_locations.name as location_name',
                    'inventory_stocks.on_hand',
                    'inventory_stocks.reserved',
                    'inventory_stocks.available',
                    'inventory_stocks.average_cost',
                    DB::raw('(inventory_stocks.on_hand * inventory_stocks.average_cost) as total_value')
                )
                ->where('inventory_stocks.company_id', $companyId);

            // Apply location filter if provided
            if (!empty($params['location_id'])) {
                $query->where('inventory_stocks.location_id', $params['location_id']);
            }

            $stocks = $query->get()->map(function($stock) {
                return [
                    'part_id' => $stock->part_id,
                    'part_name' => $stock->part_name,
                    'part_number' => $stock->part_number,
                    'location' => $stock->location_name ?? 'Unknown',
                    'on_hand' => (int) $stock->on_hand,
                    'reserved' => (int) $stock->reserved,
                    'available' => (int) $stock->available,
                    'unit_cost' => round((float) $stock->unit_cost, 2),
                    'average_cost' => round((float) $stock->average_cost, 2),
                    'total_value' => round((float) $stock->total_value, 2),
                ];
            })->toArray();

            $summary = [
                'total_items' => count($stocks),
                'total_on_hand' => array_sum(array_column($stocks, 'on_hand')),
                'total_reserved' => array_sum(array_column($stocks, 'reserved')),
                'total_available' => array_sum(array_column($stocks, 'available')),
                'total_value' => round(array_sum(array_column($stocks, 'total_value')), 2),
            ];

            return $this->formatResponse([
                'summary' => $summary,
                'stocks' => $stocks,
            ]);
        });
    }

    /**
     * Generate slow moving inventory report
     */
    private function generateSlowMoving(array $params = []): array
    {
        return $this->trackPerformance('inventory.slow_moving', function () use ($params) {
            $companyId = $this->getCompanyId();
            $daysThreshold = $params['days'] ?? 90; // Default to 90 days

            // Get parts with no transactions in the last X days
            $slowMovingItems = DB::table('inventory_stocks')
                ->join('inventory_parts', 'inventory_stocks.part_id', '=', 'inventory_parts.id')
                ->leftJoin('inventory_transactions', function($join) use ($daysThreshold) {
                    $join->on('inventory_parts.id', '=', 'inventory_transactions.part_id')
                        ->where('inventory_transactions.created_at', '>=', now()->subDays($daysThreshold));
                })
                ->select(
                    'inventory_parts.id as part_id',
                    'inventory_parts.name',
                    'inventory_parts.part_number',
                    DB::raw('SUM(inventory_stocks.on_hand) as total_on_hand'),
                    DB::raw('SUM(inventory_stocks.on_hand * inventory_stocks.average_cost) as total_value'),
                    DB::raw('MAX(inventory_transactions.created_at) as last_transaction_date'),
                    DB::raw('COUNT(inventory_transactions.id) as transaction_count')
                )
                ->where('inventory_stocks.company_id', $companyId)
                ->groupBy('inventory_parts.id', 'inventory_parts.name', 'inventory_parts.part_number')
                ->having('transaction_count', '=', 0)
                ->orHaving('transaction_count', '<', 5)
                ->get()
                ->map(function($item) {
                    return [
                        'part_id' => $item->part_id,
                        'name' => $item->name,
                        'part_number' => $item->part_number,
                        'total_on_hand' => (int) $item->total_on_hand,
                        'total_value' => round((float) $item->total_value, 2),
                        'last_transaction_date' => $item->last_transaction_date,
                        'transaction_count' => (int) $item->transaction_count,
                        'days_since_last_transaction' => $item->last_transaction_date 
                            ? now()->diffInDays($item->last_transaction_date)
                            : null,
                    ];
                })->toArray();

            $summary = [
                'total_slow_moving_items' => count($slowMovingItems),
                'total_value_tied_up' => round(array_sum(array_column($slowMovingItems, 'total_value')), 2),
                'days_threshold' => $daysThreshold,
            ];

            return $this->formatResponse([
                'summary' => $summary,
                'items' => $slowMovingItems,
            ]);
        });
    }

    /**
     * Generate reorder analysis report
     */
    private function generateReorderAnalysis(array $params = []): array
    {
        return $this->trackPerformance('inventory.reorder_analysis', function () use ($params) {
            $companyId = $this->getCompanyId();

            $reorderItems = DB::table('inventory_stocks')
                ->join('inventory_parts', 'inventory_stocks.part_id', '=', 'inventory_parts.id')
                ->leftJoin('inventory_locations', 'inventory_stocks.location_id', '=', 'inventory_locations.id')
                ->select(
                    'inventory_parts.id as part_id',
                    'inventory_parts.name',
                    'inventory_parts.part_number',
                    'inventory_parts.reorder_point',
                    'inventory_parts.reorder_qty',
                    'inventory_locations.name as location_name',
                    'inventory_stocks.on_hand',
                    'inventory_stocks.available',
                    'inventory_stocks.average_cost'
                )
                ->where('inventory_stocks.company_id', $companyId)
                ->whereNotNull('inventory_parts.reorder_point')
                ->whereRaw('inventory_stocks.available <= inventory_parts.reorder_point')
                ->get()
                ->map(function($item) {
                    $recommendedOrderQty = max(
                        $item->reorder_qty ?? 0,
                        ($item->reorder_point ?? 0) - $item->available
                    );
                    
                    return [
                        'part_id' => $item->part_id,
                        'name' => $item->name,
                        'part_number' => $item->part_number,
                        'location' => $item->location_name ?? 'Unknown',
                        'on_hand' => (int) $item->on_hand,
                        'available' => (int) $item->available,
                        'reorder_point' => (int) $item->reorder_point,
                        'reorder_qty' => (int) $item->reorder_qty,
                        'recommended_order_qty' => (int) $recommendedOrderQty,
                        'estimated_cost' => round((float) $item->average_cost * $recommendedOrderQty, 2),
                    ];
                })->toArray();

            $summary = [
                'total_items_to_reorder' => count($reorderItems),
                'total_estimated_cost' => round(array_sum(array_column($reorderItems, 'estimated_cost')), 2),
                'total_recommended_quantity' => array_sum(array_column($reorderItems, 'recommended_order_qty')),
            ];

            return $this->formatResponse([
                'summary' => $summary,
                'items' => $reorderItems,
            ]);
        });
    }
}


