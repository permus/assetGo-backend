<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AssetCacheService
{
    /**
     * Cache duration in seconds (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Get asset statistics with caching
     *
     * @param int $companyId
     * @param callable $callback
     * @return mixed
     */
    public function getStatistics(int $companyId, callable $callback)
    {
        return Cache::remember(
            "asset-statistics-{$companyId}",
            self::CACHE_TTL,
            $callback
        );
    }

    /**
     * Get asset analytics with caching
     *
     * @param int $companyId
     * @param callable $callback
     * @return mixed
     */
    public function getAnalytics(int $companyId, callable $callback)
    {
        return Cache::remember(
            "asset-analytics-{$companyId}",
            self::CACHE_TTL,
            $callback
        );
    }

    /**
     * Get chart data with caching
     *
     * @param int $assetId
     * @param callable $callback
     * @return mixed
     */
    public function getChartData(int $assetId, callable $callback)
    {
        return Cache::remember(
            "asset-chart-{$assetId}",
            self::CACHE_TTL,
            $callback
        );
    }

    /**
     * Get public statistics with caching
     *
     * @param callable $callback
     * @return mixed
     */
    public function getPublicStatistics(callable $callback)
    {
        return Cache::remember(
            "asset-public-statistics",
            self::CACHE_TTL,
            $callback
        );
    }

    /**
     * Clear all asset caches for a company
     *
     * @param int $companyId
     * @return void
     */
    public function clearCompanyCache(int $companyId): void
    {
        Cache::forget("asset-statistics-{$companyId}");
        Cache::forget("asset-analytics-{$companyId}");
    }

    /**
     * Clear cache for a specific asset
     *
     * @param int $assetId
     * @return void
     */
    public function clearAssetCache(int $assetId): void
    {
        Cache::forget("asset-chart-{$assetId}");
    }

    /**
     * Clear all asset caches
     *
     * @return void
     */
    public function clearAll(): void
    {
        Cache::flush();
    }
}

