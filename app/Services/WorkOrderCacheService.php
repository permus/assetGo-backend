<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class WorkOrderCacheService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get cached analytics data for a company
     *
     * @param int $companyId
     * @param callable $callback
     * @return mixed
     */
    public function getAnalytics(int $companyId, callable $callback)
    {
        return Cache::remember(
            "work-order-analytics-{$companyId}",
            self::CACHE_TTL,
            $callback
        );
    }

    /**
     * Get cached statistics data for a company
     *
     * @param int $companyId
     * @param callable $callback
     * @return mixed
     */
    public function getStatistics(int $companyId, callable $callback)
    {
        return Cache::remember(
            "work-order-statistics-{$companyId}",
            self::CACHE_TTL,
            $callback
        );
    }

    /**
     * Clear all work order cache for a company
     *
     * @param int $companyId
     * @return void
     */
    public function clearCompanyCache(int $companyId): void
    {
        Cache::forget("work-order-analytics-{$companyId}");
        Cache::forget("work-order-statistics-{$companyId}");
    }
}

