<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrder;
use App\Models\WorkOrderTimeLog;

class TeamCacheService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_PREFIX = 'team_';

    /**
     * Get or cache team analytics
     */
    public function getAnalytics(int $companyId, int $days): array
    {
        $cacheKey = self::CACHE_PREFIX . "analytics_{$companyId}_{$days}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($companyId, $days) {
            return $this->computeAnalytics($companyId, $days);
        });
    }

    /**
     * Get or cache team statistics
     */
    public function getStatistics(int $companyId): array
    {
        $cacheKey = self::CACHE_PREFIX . "statistics_{$companyId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($companyId) {
            return $this->computeStatistics($companyId);
        });
    }

    /**
     * Clear all team-related cache for a company
     */
    public function clearCompanyCache(int $companyId): void
    {
        // Clear statistics cache
        Cache::forget(self::CACHE_PREFIX . "statistics_{$companyId}");

        // Clear analytics cache for common date ranges
        $commonRanges = [7, 14, 30, 60, 90];
        foreach ($commonRanges as $days) {
            Cache::forget(self::CACHE_PREFIX . "analytics_{$companyId}_{$days}");
        }

        \Log::info('Team cache cleared', [
            'company_id' => $companyId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Compute team analytics
     */
    private function computeAnalytics(int $companyId, int $days): array
    {
        $end = now();
        $start = (clone $end)->subDays(max(1, $days));

        // Productivity: completed assignments / total assignments in range
        $totalAssigned = WorkOrderAssignment::join('work_orders', 'work_order_assignments.work_order_id', '=', 'work_orders.id')
            ->where('work_orders.company_id', $companyId)
            ->whereBetween('work_order_assignments.created_at', [$start, $end])
            ->count();

        $completedAssigned = WorkOrderAssignment::join('work_orders', 'work_order_assignments.work_order_id', '=', 'work_orders.id')
            ->where('work_orders.company_id', $companyId)
            ->where('work_order_assignments.status', 'completed')
            ->whereBetween('work_order_assignments.updated_at', [$start, $end])
            ->count();

        $productivity = $totalAssigned > 0 ? round(($completedAssigned / $totalAssigned) * 100, 2) : 0.0;

        // Avg completion time (days) for work orders completed in range
        $avgCompletionDays = (float) (WorkOrder::where('company_id', $companyId)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, created_at, completed_at)) as avg_days')
            ->value('avg_days') ?? 0);
        $avgCompletionDays = round($avgCompletionDays, 1);

        // On-time rate: completed by due_date among completed in range
        $completedInRange = WorkOrder::where('company_id', $companyId)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end]);
        $completedCount = (int) $completedInRange->count();
        $onTimeCount = (int) WorkOrder::where('company_id', $companyId)
            ->whereNotNull('completed_at')
            ->whereNotNull('due_date')
            ->whereBetween('completed_at', [$start, $end])
            ->whereColumn('completed_at', '<=', 'due_date')
            ->count();
        $onTimeRate = $completedCount > 0 ? round(($onTimeCount / $completedCount) * 100, 2) : 0.0;

        // Labor cost from time logs in range
        $laborCost = (float) WorkOrderTimeLog::where('company_id', $companyId)
            ->whereBetween('start_time', [$start, $end])
            ->selectRaw('COALESCE(SUM(COALESCE(total_cost, (duration_minutes/60)*hourly_rate)), 0) as total')
            ->value('total');

        // Top performers by completed assignments
        $topPerformers = WorkOrderAssignment::join('work_orders', 'work_order_assignments.work_order_id', '=', 'work_orders.id')
            ->join('users', 'users.id', '=', 'work_order_assignments.user_id')
            ->where('work_orders.company_id', $companyId)
            ->where('work_order_assignments.status', 'completed')
            ->whereBetween('work_order_assignments.updated_at', [$start, $end])
            ->groupBy('work_order_assignments.user_id', 'users.first_name', 'users.last_name')
            ->select([
                'work_order_assignments.user_id as user_id',
                DB::raw('users.first_name as first_name'),
                DB::raw('users.last_name as last_name'),
                DB::raw('COUNT(*) as completed_count'),
            ])
            ->orderByDesc('completed_count')
            ->limit(5)
            ->get();

        return [
            'date_range_days' => $days,
            'productivity_rate_percent' => $productivity,
            'on_time_rate_percent' => $onTimeRate,
            'avg_completion_days' => $avgCompletionDays,
            'labor_cost_total' => round($laborCost, 2),
            'top_performers' => $topPerformers,
        ];
    }

    /**
     * Compute team statistics
     */
    private function computeStatistics(int $companyId): array
    {
        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            return [];
        }

        $totalTeamMembers = $company->users()->where('user_type', 'user')->count();
        $activeTeamMembers = $company->users()->where('user_type', 'user')->whereNotNull('email_verified_at')->count();
        $pendingTeamMembers = $company->users()->where('user_type', 'user')->whereNull('email_verified_at')->count();

        // Aggregate work order assignment counts for this company
        $assignmentAggregates = WorkOrderAssignment::join('work_orders', 'work_order_assignments.work_order_id', '=', 'work_orders.id')
            ->where('work_orders.company_id', $companyId)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw("SUM(CASE WHEN work_order_assignments.status IN ('assigned','accepted') THEN 1 ELSE 0 END) as active_count")
            ->selectRaw("SUM(CASE WHEN work_order_assignments.status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->first();

        $totalAssignments = (int) ($assignmentAggregates->total_count ?? 0);
        $activeAssignments = (int) ($assignmentAggregates->active_count ?? 0);
        $completedAssignments = (int) ($assignmentAggregates->completed_count ?? 0);
        $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 2) : 0;

        return [
            'total_team_members' => $totalTeamMembers,
            'active_team_members' => $activeTeamMembers,
            'pending_team_members' => $pendingTeamMembers,
            'assigned_work_orders_total_count' => $totalAssignments,
            'assigned_work_orders_active_count' => $activeAssignments,
            'assigned_work_orders_completed_count' => $completedAssignments,
            'completion_rate' => $completionRate,
        ];
    }
}

