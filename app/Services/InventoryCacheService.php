<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class InventoryCacheService
{
    private const CACHE_TTL_5MIN = 300;   // 5 minutes
    private const CACHE_TTL_10MIN = 600;  // 10 minutes
    private const CACHE_TTL_15MIN = 900;  // 15 minutes
    private const CACHE_TTL_30MIN = 1800; // 30 minutes

    /**
     * Get cached parts overview (total parts, low stock, total value)
     *
     * @param int $companyId
     * @param callable $callback
     * @return mixed
     */
    public function getPartsOverview(int $companyId, callable $callback)
    {
        return Cache::remember(
            "inventory-parts-overview-{$companyId}",
            self::CACHE_TTL_5MIN,
            $callback
        );
    }

    /**
     * Get cached purchase order overview (statistics by status)
     *
     * @param int $companyId
     * @param callable $callback
     * @return mixed
     */
    public function getPurchaseOrderOverview(int $companyId, callable $callback)
    {
        return Cache::remember(
            "inventory-po-overview-{$companyId}",
            self::CACHE_TTL_5MIN,
            $callback
        );
    }

    /**
     * Get cached analytics dashboard data
     *
     * @param int $companyId
     * @param callable $callback
     * @return mixed
     */
    public function getAnalyticsDashboard(int $companyId, callable $callback)
    {
        return Cache::remember(
            "inventory-analytics-dashboard-{$companyId}",
            self::CACHE_TTL_10MIN,
            $callback
        );
    }

    /**
     * Get cached KPIs (turnover, carrying cost, dead stock)
     *
     * @param int $companyId
     * @param string $period
     * @param callable $callback
     * @return mixed
     */
    public function getKPIs(int $companyId, string $period, callable $callback)
    {
        return Cache::remember(
            "inventory-kpis-{$companyId}-{$period}",
            self::CACHE_TTL_15MIN,
            $callback
        );
    }

    /**
     * Get cached ABC analysis results
     *
     * @param int $companyId
     * @param callable $callback
     * @return mixed
     */
    public function getABCAnalysis(int $companyId, callable $callback)
    {
        return Cache::remember(
            "inventory-abc-analysis-{$companyId}",
            self::CACHE_TTL_30MIN,
            $callback
        );
    }

    /**
     * Clear all inventory cache for a company
     *
     * @param int $companyId
     * @return void
     */
    public function clearCompanyCache(int $companyId): void
    {
        Cache::forget("inventory-parts-overview-{$companyId}");
        Cache::forget("inventory-po-overview-{$companyId}");
        Cache::forget("inventory-analytics-dashboard-{$companyId}");
        
        // Clear KPIs for all periods
        foreach (['1m', '3m', '6m', '1y'] as $period) {
            Cache::forget("inventory-kpis-{$companyId}-{$period}");
        }
        
        Cache::forget("inventory-abc-analysis-{$companyId}");
    }

    /**
     * Clear part-related caches
     *
     * @param int $companyId
     * @return void
     */
    public function clearPartCache(int $companyId): void
    {
        Cache::forget("inventory-parts-overview-{$companyId}");
        Cache::forget("inventory-analytics-dashboard-{$companyId}");
        Cache::forget("inventory-abc-analysis-{$companyId}");
    }

    /**
     * Clear stock-related caches
     *
     * @param int $companyId
     * @return void
     */
    public function clearStockCache(int $companyId): void
    {
        Cache::forget("inventory-parts-overview-{$companyId}");
        Cache::forget("inventory-analytics-dashboard-{$companyId}");
        
        // Clear KPIs for all periods
        foreach (['1m', '3m', '6m', '1y'] as $period) {
            Cache::forget("inventory-kpis-{$companyId}-{$period}");
        }
        
        Cache::forget("inventory-abc-analysis-{$companyId}");
    }

    /**
     * Clear purchase order related caches
     *
     * @param int $companyId
     * @return void
     */
    public function clearPurchaseOrderCache(int $companyId): void
    {
        Cache::forget("inventory-po-overview-{$companyId}");
        Cache::forget("inventory-analytics-dashboard-{$companyId}");
    }
}

