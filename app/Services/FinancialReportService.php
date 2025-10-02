<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialReportService extends ReportService
{
    public function generateReport(string $reportKey, array $params = []): array
    {
        return match($reportKey) {
            'financial.total_cost_ownership' => $this->generateTco($params),
            'financial.maintenance_cost_breakdown' => $this->generateMaintenanceCostBreakdown($params),
            'financial.budget.vs.actual' => $this->generateBudgetVsActual($params),
            default => throw new \InvalidArgumentException("Unknown report key: {$reportKey}")
        };
    }

    public function getAvailableReports(): array
    {
        return [
            'financial.total_cost_ownership' => ['key' => 'financial.total_cost_ownership', 'name' => 'Total Cost of Ownership'],
            'financial.maintenance_cost_breakdown' => ['key' => 'financial.maintenance_cost_breakdown', 'name' => 'Maintenance Cost Breakdown'],
            'financial.budget.vs.actual' => ['key' => 'financial.budget.vs.actual', 'name' => 'Budget vs Actual'],
        ];
    }

    private function generateTco(array $params): array
    {
        return $this->trackPerformance('financial.total_cost_ownership', function () use ($params) {
            $companyId = $this->getCompanyId();
            $filters = $this->extractFilters($params);

            $start = $filters['date_from'] ?? null;
            $end = $filters['date_to'] ?? null;

            // Acquisition cost: sum of initial asset purchase costs (assets table)
            $acquisition = (float) DB::table('assets')
                ->where('company_id', $companyId)
                ->when($start, fn($q) => $q->where('created_at', '>=', $start))
                ->when($end, fn($q) => $q->where('created_at', '<=', $end))
                ->selectRaw('COALESCE(SUM(purchase_cost), 0) as total')
                ->value('total');

            // Maintenance cost proxy: sum actual_hours * 50 across work orders in range
            $maintenance = (float) DB::table('work_orders')
                ->where('company_id', $companyId)
                ->when($start, fn($q) => $q->where('created_at', '>=', $start))
                ->when($end, fn($q) => $q->where('created_at', '<=', $end))
                ->selectRaw('COALESCE(SUM(COALESCE(actual_hours,0) * 50), 0) as total')
                ->value('total');

            // Disposal (placeholder): count of disposed assets * nominal fee
            $disposal = (float) DB::table('assets')
                ->where('company_id', $companyId)
                ->where('status', 'disposed')
                ->when($start, fn($q) => $q->where('updated_at', '>=', $start))
                ->when($end, fn($q) => $q->where('updated_at', '<=', $end))
                ->selectRaw('COUNT(*) * 100 as total')
                ->value('total');

            $tco = $acquisition + $maintenance + $disposal;

            return $this->formatResponse([
                'summary' => [
                    'acquisition' => round($acquisition, 2),
                    'maintenance' => round($maintenance, 2),
                    'disposal' => round($disposal, 2),
                    'tco' => round($tco, 2),
                ],
            ]);
        });
    }

    private function generateMaintenanceCostBreakdown(array $params): array
    {
        return $this->trackPerformance('financial.maintenance_cost_breakdown', function () use ($params) {
            $companyId = $this->getCompanyId();
            $filters = $this->extractFilters($params);

            $start = $filters['date_from'] ?? null;
            $end = $filters['date_to'] ?? null;

            // Cost by asset category (proxy: hours * 50)
            $byCategory = DB::table('work_orders')
                ->join('assets', 'work_orders.asset_id', '=', 'assets.id')
                ->leftJoin('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
                ->where('work_orders.company_id', $companyId)
                ->when($start, fn($q) => $q->where('work_orders.created_at', '>=', $start))
                ->when($end, fn($q) => $q->where('work_orders.created_at', '<=', $end))
                ->groupBy('asset_categories.id', 'asset_categories.name')
                ->selectRaw('COALESCE(asset_categories.name, "Uncategorized") as category, COALESCE(SUM(COALESCE(work_orders.actual_hours,0) * 50), 0) as total')
                ->orderByDesc('total')
                ->get();

            return $this->formatResponse([
                'cost_by_category' => $byCategory,
            ]);
        });
    }

    private function generateBudgetVsActual(array $params): array
    {
        return $this->trackPerformance('financial.budget.vs.actual', function () use ($params) {
            $companyId = $this->getCompanyId();
            $filters = $this->extractFilters($params);

            $start = !empty($filters['date_from']) ? Carbon::parse($filters['date_from']) : Carbon::now()->startOfMonth();
            $end = !empty($filters['date_to']) ? Carbon::parse($filters['date_to']) : Carbon::now()->endOfMonth();

            // Months span (min 1)
            $months = max(1, (int) ceil($start->diffInDays($end) / 30) );

            // Actual: maintenance cost proxy within date range (hours * 50)
            $actual = (float) DB::table('work_orders')
                ->where('company_id', $companyId)
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(COALESCE(actual_hours,0) * 50), 0) as total')
                ->value('total');

            // Budget: monthly baseline from env or default, multiplied by months
            $monthlyBudget = (float) env('FINANCIAL_BUDGET_MONTHLY', 50000);
            $budget = $monthlyBudget * $months;

            $variance = $actual - $budget;
            $variancePct = $budget > 0 ? (($variance / $budget) * 100) : 0;

            return $this->formatResponse([
                'summary' => [
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'months' => $months,
                    'budget' => round($budget, 2),
                    'actual' => round($actual, 2),
                    'variance' => round($variance, 2),
                    'variance_pct' => round($variancePct, 2),
                ]
            ]);
        });
    }
}


