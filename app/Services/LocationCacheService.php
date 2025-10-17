<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class LocationCacheService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Get location hierarchy with caching
     */
    public function getHierarchy(int $companyId, callable $callback)
    {
        return Cache::remember(
            "location-hierarchy-{$companyId}",
            self::CACHE_TTL,
            $callback
        );
    }

    /**
     * Clear all location caches for a company
     */
    public function clearCompanyCache(int $companyId): void
    {
        Cache::forget("location-hierarchy-{$companyId}");
    }
}

