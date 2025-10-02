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
            'inventory.abc.analysis' => $this->generateAbcAnalysis($params),
            default => throw new \InvalidArgumentException("Unknown report key: {$reportKey}")
        };
    }

    /**
     * Available inventory reports
     */
    public function getAvailableReports(): array
    {
        return [
            'inventory.abc.analysis' => [
                'key' => 'inventory.abc.analysis',
                'name' => 'ABC Analysis',
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
}


