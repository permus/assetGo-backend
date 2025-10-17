<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\MaintenancePlan;
use App\Models\ScheduleMaintenance;

class MaintenanceCacheService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_PREFIX = 'maintenance_';

    /**
     * Get or cache active plans count
     */
    public function getActivePlansCount(int $companyId): int
    {
        $cacheKey = self::CACHE_PREFIX . "active_plans_count_{$companyId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($companyId) {
            return MaintenancePlan::where('company_id', $companyId)
                ->where('is_active', true)
                ->count();
        });
    }

    /**
     * Get or cache maintenance plans statistics
     */
    public function getPlansStatistics(int $companyId): array
    {
        $cacheKey = self::CACHE_PREFIX . "plans_stats_{$companyId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($companyId) {
            return [
                'total_plans' => MaintenancePlan::where('company_id', $companyId)->count(),
                'active_plans' => MaintenancePlan::where('company_id', $companyId)->where('is_active', true)->count(),
                'inactive_plans' => MaintenancePlan::where('company_id', $companyId)->where('is_active', false)->count(),
                'preventive_plans' => MaintenancePlan::where('company_id', $companyId)->where('plan_type', 'preventive')->count(),
                'predictive_plans' => MaintenancePlan::where('company_id', $companyId)->where('plan_type', 'predictive')->count(),
                'condition_based_plans' => MaintenancePlan::where('company_id', $companyId)->where('plan_type', 'condition_based')->count(),
            ];
        });
    }

    /**
     * Get or cache schedule statistics
     */
    public function getScheduleStatistics(int $companyId): array
    {
        $cacheKey = self::CACHE_PREFIX . "schedule_stats_{$companyId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($companyId) {
            // Get plans for this company
            $planIds = MaintenancePlan::where('company_id', $companyId)->pluck('id');

            return [
                'total_schedules' => ScheduleMaintenance::whereIn('maintenance_plan_id', $planIds)->count(),
                'pending_schedules' => ScheduleMaintenance::whereIn('maintenance_plan_id', $planIds)->where('status', 'pending')->count(),
                'in_progress_schedules' => ScheduleMaintenance::whereIn('maintenance_plan_id', $planIds)->where('status', 'in_progress')->count(),
                'completed_schedules' => ScheduleMaintenance::whereIn('maintenance_plan_id', $planIds)->where('status', 'completed')->count(),
                'overdue_schedules' => ScheduleMaintenance::whereIn('maintenance_plan_id', $planIds)
                    ->where('status', '!=', 'completed')
                    ->where('due_date', '<', now())
                    ->count(),
            ];
        });
    }

    /**
     * Clear all maintenance-related cache for a company
     */
    public function clearCompanyCache(int $companyId): void
    {
        $keys = [
            self::CACHE_PREFIX . "active_plans_count_{$companyId}",
            self::CACHE_PREFIX . "plans_stats_{$companyId}",
            self::CACHE_PREFIX . "schedule_stats_{$companyId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        \Log::info('Maintenance cache cleared', [
            'company_id' => $companyId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Clear plan-specific cache
     */
    public function clearPlanCache(int $planId): void
    {
        $plan = MaintenancePlan::find($planId);
        if ($plan) {
            $this->clearCompanyCache($plan->company_id);
        }
    }
}

