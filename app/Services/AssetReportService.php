<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Location;
use App\Models\AssetCategory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetReportService extends ReportService
{
    /**
     * Generate asset summary report
     */
    public function generateSummary(array $params = []): array
    {
        return $this->trackPerformance('assets.summary', function() use ($params) {
            $filters = $this->extractFilters($params);
            $this->validateDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);

            // Use caching for read-only report data (5 minutes TTL)
            $cacheKey = $this->buildCacheKey('assets.summary', $params);
            
            return $this->getCachedData($cacheKey, function() use ($filters, $params) {
                $query = $this->buildQuery(Asset::class, $filters)
                    ->with(['location:id,name', 'category:id,name', 'assetStatus:id,name']); // Eager load with specific columns

                // Apply additional asset-specific filters
                if (!empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }

                if (!empty($filters['category_id'])) {
                    $query->where('category_id', $filters['category_id']);
                }

                // For PDF exports, fetch all data without pagination
                $format = $params['format'] ?? 'json';
                
                if ($format === 'pdf' || !empty($params['all_data'])) {
                    // Fetch all assets for PDF export
                    $assets = $query->get();
                } else {
                    $pageSize = $params['page_size'] ?? 50;
                    $assets = $this->applyPagination($query, $params['page'] ?? 1, $pageSize);
                }

                // Calculate totals (clone query to avoid pagination affecting totals)
                $totalsQuery = clone $query;
                $totals = $this->calculateAssetTotals($totalsQuery);

                // Get status distribution
                $statusDistribution = $this->getStatusDistribution($filters);

                // Get category distribution
                $categoryDistribution = $this->getCategoryDistribution($filters);

                // Handle both paginated and non-paginated results
                $assetsArray = $assets instanceof \Illuminate\Pagination\LengthAwarePaginator
                    ? collect($assets->items())->map(fn($asset) => $asset->toArray())->all()
                    : collect($assets)->map(fn($asset) => $asset->toArray())->all();
                
                return $this->formatResponse([
                    'assets' => $assetsArray,
                    'totals' => $totals,
                    'status_distribution' => $statusDistribution,
                    'category_distribution' => $categoryDistribution,
                    'pagination' => $assets instanceof \Illuminate\Pagination\LengthAwarePaginator 
                        ? $this->getPaginationMeta($assets) 
                        : null
                ]);
            }, 300); // 5 minutes cache
        });
    }

    /**
     * Generate asset utilization report
     */
    public function generateUtilization(array $params = []): array
    {
        return $this->trackPerformance('assets.utilization', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            // This would typically involve work order data to calculate utilization
            // For now, we'll provide a basic structure
            $query = $this->buildQuery(Asset::class, $filters)
                ->with(['location', 'category']);

            $assets = $this->applyPagination($query, $params['page'] ?? 1, $params['page_size'] ?? 50);

            // Calculate utilization metrics (placeholder - would need work order integration)
            $utilizationData = $this->calculateUtilizationMetrics($assets->items());

            return $this->formatResponse([
                'assets' => $utilizationData,
                'pagination' => $this->getPaginationMeta($assets)
            ]);
        });
    }

    /**
     * Generate asset depreciation report
     */
    public function generateDepreciation(array $params = []): array
    {
        return $this->trackPerformance('assets.depreciation', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            $query = $this->buildQuery(Asset::class, $filters)
                ->whereNotNull('purchase_price')
                ->whereNotNull('purchase_date')
                ->whereNotNull('depreciation_life')
                ->with(['location', 'category']);

            $assets = $this->applyPagination($query, $params['page'] ?? 1, $params['page_size'] ?? 50);

            // Calculate depreciation for each asset
            $depreciationData = $this->calculateDepreciationData($assets->items());

            // Calculate totals
            $totals = $this->calculateDepreciationTotals($depreciationData);

            return $this->formatResponse([
                'assets' => $depreciationData,
                'totals' => $totals,
                'pagination' => $this->getPaginationMeta($assets)
            ]);
        });
    }

    /**
     * Generate asset warranty report
     */
    public function generateWarranty(array $params = []): array
    {
        return $this->trackPerformance('assets.warranty', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            $query = $this->buildQuery(Asset::class, $filters)
                ->whereNotNull('warranty')
                ->with(['location', 'category']);

            $assets = $this->applyPagination($query, $params['page'] ?? 1, $params['page_size'] ?? 50);

            // Calculate warranty status for each asset
            $warrantyData = $this->calculateWarrantyData($assets->items());

            // Get warranty summary
            $warrantySummary = $this->getWarrantySummary($warrantyData);

            return $this->formatResponse([
                'assets' => $warrantyData,
                'summary' => $warrantySummary,
                'pagination' => $this->getPaginationMeta($assets)
            ]);
        });
    }

    /**
     * Generate asset compliance report
     */
    public function generateCompliance(array $params = []): array
    {
        return $this->trackPerformance('assets.compliance', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            $query = $this->buildQuery(Asset::class, $filters)
                ->with(['location', 'category']);

            $assets = $this->applyPagination($query, $params['page'] ?? 1, $params['page_size'] ?? 50);

            // Calculate compliance metrics (placeholder - would need compliance data)
            $complianceData = $this->calculateComplianceData($assets->items());

            return $this->formatResponse([
                'assets' => $complianceData,
                'pagination' => $this->getPaginationMeta($assets)
            ]);
        });
    }

    /**
     * Calculate asset totals
     */
    private function calculateAssetTotals($query): array
    {
        $baseQuery = clone $query;
        
        return [
            'total_count' => $baseQuery->count(),
            'total_value' => $baseQuery->sum('purchase_price') ?? 0,
            'average_value' => $baseQuery->avg('purchase_price') ?? 0,
            'active_count' => $baseQuery->where('status', 'active')->count(),
            'maintenance_count' => $baseQuery->where('status', 'maintenance')->count(),
            'inactive_count' => $baseQuery->where('status', 'inactive')->count()
        ];
    }

    /**
     * Get status distribution
     */
    private function getStatusDistribution(array $filters): array
    {
        $query = $this->buildQuery(Asset::class, $filters);
        
        return $query->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            })
            ->toArray();
    }

    /**
     * Get category distribution
     */
    private function getCategoryDistribution(array $filters): array
    {
        $query = Asset::forCompany($this->getCompanyId())
            ->join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
            ->select('asset_categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('asset_categories.id', 'asset_categories.name');
        
        // Apply date filters with proper table qualification
        if (!empty($filters['date_from'])) {
            $query->where('assets.created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('assets.created_at', '<=', $filters['date_to']);
        }
        
        // Apply other filters
        if (!empty($filters['location_ids'])) {
            $query->whereIn('assets.location_id', $filters['location_ids']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('assets.status', $filters['status']);
        }
        
        if (!empty($filters['category_id'])) {
            $query->where('assets.category_id', $filters['category_id']);
        }
        
        return $query->get()
            ->mapWithKeys(function ($item) {
                return [$item->name => $item->count];
            })
            ->toArray();
    }

    /**
     * Calculate utilization metrics
     */
    private function calculateUtilizationMetrics(array $assets): array
    {
        // Placeholder implementation - would need work order integration
        return array_map(function ($asset) {
            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'location' => $asset->location?->name ?? 'N/A',
                'category' => $asset->category?->name ?? 'N/A',
                'utilization_rate' => rand(60, 95), // Placeholder
                'hours_used' => rand(100, 2000), // Placeholder
                'hours_available' => 8760, // 24/7 for a year
                'status' => $asset->status
            ];
        }, $assets);
    }

    /**
     * Calculate depreciation data
     */
    private function calculateDepreciationData(array $assets): array
    {
        return array_map(function ($asset) {
            $purchaseDate = Carbon::parse($asset->purchase_date);
            $monthsElapsed = $purchaseDate->diffInMonths(now());
            $depreciationLife = $asset->depreciation_life ?? 60; // Default 5 years
            
            $monthlyDepreciation = $asset->purchase_price / $depreciationLife;
            $accumulatedDepreciation = $monthlyDepreciation * $monthsElapsed;
            $bookValue = $asset->purchase_price - $accumulatedDepreciation;

            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'purchase_price' => $asset->purchase_price,
                'purchase_date' => $asset->purchase_date,
                'depreciation_life' => $depreciationLife,
                'monthly_depreciation' => round($monthlyDepreciation, 2),
                'accumulated_depreciation' => round($accumulatedDepreciation, 2),
                'book_value' => round(max($bookValue, 0), 2),
                'months_elapsed' => $monthsElapsed,
                'location' => $asset->location?->name ?? 'N/A'
            ];
        }, $assets);
    }

    /**
     * Calculate depreciation totals
     */
    private function calculateDepreciationTotals(array $depreciationData): array
    {
        $totalPurchasePrice = array_sum(array_column($depreciationData, 'purchase_price'));
        $totalAccumulatedDepreciation = array_sum(array_column($depreciationData, 'accumulated_depreciation'));
        $totalBookValue = array_sum(array_column($depreciationData, 'book_value'));

        return [
            'total_purchase_price' => $totalPurchasePrice,
            'total_accumulated_depreciation' => $totalAccumulatedDepreciation,
            'total_book_value' => $totalBookValue,
            'depreciation_percentage' => $totalPurchasePrice > 0 ? 
                round(($totalAccumulatedDepreciation / $totalPurchasePrice) * 100, 2) : 0
        ];
    }

    /**
     * Calculate warranty data
     */
    private function calculateWarrantyData(array $assets): array
    {
        return array_map(function ($asset) {
            // Handle different warranty formats
            $warrantyValue = $asset->warranty;
            $warrantyEndDate = null;
            $daysToExpire = 0;
            $status = 'no_warranty';
            
            if ($warrantyValue) {
                try {
                    // Try to parse as date first
                    if (is_numeric($warrantyValue)) {
                        // If it's a number, treat it as days from purchase date
                        $purchaseDate = $asset->purchase_date ? Carbon::parse($asset->purchase_date) : now();
                        $warrantyEndDate = $purchaseDate->addDays($warrantyValue);
                    } else {
                        // Try to parse as date string
                        $warrantyEndDate = Carbon::parse($warrantyValue);
                    }
                    
                    $daysToExpire = now()->diffInDays($warrantyEndDate, false);
                    
                    if ($daysToExpire < 0) {
                        $status = 'expired';
                    } elseif ($daysToExpire <= 30) {
                        $status = 'expiring_soon';
                    } else {
                        $status = 'active';
                    }
                } catch (\Exception $e) {
                    // If parsing fails, treat as no warranty
                    $status = 'invalid_warranty';
                    $warrantyEndDate = null;
                    $daysToExpire = 0;
                }
            }

            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'warranty_end_date' => $warrantyEndDate ? $warrantyEndDate->toDateString() : null,
                'days_to_expire' => $daysToExpire,
                'status' => $status,
                'location' => $asset->location?->name ?? 'N/A',
                'category' => $asset->category?->name ?? 'N/A'
            ];
        }, $assets);
    }

    /**
     * Get warranty summary
     */
    private function getWarrantySummary(array $warrantyData): array
    {
        $total = count($warrantyData);
        $active = count(array_filter($warrantyData, fn($item) => $item['status'] === 'active'));
        $expiringSoon = count(array_filter($warrantyData, fn($item) => $item['status'] === 'expiring_soon'));
        $expired = count(array_filter($warrantyData, fn($item) => $item['status'] === 'expired'));

        return [
            'total' => $total,
            'active' => $active,
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
            'active_percentage' => $total > 0 ? round(($active / $total) * 100, 2) : 0
        ];
    }

    /**
     * Calculate compliance data
     */
    private function calculateComplianceData(array $assets): array
    {
        // Placeholder implementation - would need compliance tracking
        return array_map(function ($asset) {
            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'compliance_status' => 'compliant', // Placeholder
                'last_inspection' => now()->subDays(rand(1, 365))->toDateString(),
                'next_inspection' => now()->addDays(rand(30, 365))->toDateString(),
                'location' => $asset->location?->name ?? 'N/A'
            ];
        }, $assets);
    }

    /**
     * Generate report data
     */
    public function generateReport(string $reportKey, array $params = []): array
    {
        return match($reportKey) {
            // New format (from frontend)
            'assets.asset-summary' => $this->generateSummary($params),
            'assets.asset-utilization' => $this->generateUtilization($params),
            'assets.depreciation-analysis' => $this->generateDepreciation($params),
            'assets.warranty-status' => $this->generateWarranty($params),
            'assets.compliance-report' => $this->generateCompliance($params),
            // Old format (for backward compatibility)
            'assets.summary' => $this->generateSummary($params),
            'assets.utilization' => $this->generateUtilization($params),
            'assets.depreciation' => $this->generateDepreciation($params),
            'assets.warranty' => $this->generateWarranty($params),
            'assets.compliance' => $this->generateCompliance($params),
            default => throw new \InvalidArgumentException("Unknown report key: {$reportKey}")
        };
    }

    /**
     * Get available asset reports
     */
    public function getAvailableReports(): array
    {
        return [
            'assets.summary' => [
                'name' => 'Asset Summary',
                'description' => 'Overview of all assets with counts, values, and distributions',
                'category' => 'assets'
            ],
            'assets.utilization' => [
                'name' => 'Asset Utilization',
                'description' => 'Asset usage rates and efficiency metrics',
                'category' => 'assets'
            ],
            'assets.depreciation' => [
                'name' => 'Asset Depreciation',
                'description' => 'Depreciation schedules and book values',
                'category' => 'assets'
            ],
            'assets.warranty' => [
                'name' => 'Asset Warranty',
                'description' => 'Warranty status and expiration tracking',
                'category' => 'assets'
            ],
            'assets.compliance' => [
                'name' => 'Asset Compliance',
                'description' => 'Regulatory compliance status',
                'category' => 'assets'
            ]
        ];
    }
}
