<?php

namespace App\Services;

use App\Models\ReportRun;
use App\Models\ReportTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

abstract class ReportService
{
    protected ?int $companyId = null;
    protected ?int $userId = null;

    public function __construct()
    {
        // User data will be set when methods are called
    }

    /**
     * Get current user's company ID
     */
    protected function getCompanyId(): int
    {
        if ($this->companyId === null) {
            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }
            $this->companyId = $user->company_id;
        }
        return $this->companyId;
    }

    /**
     * Get current user's ID
     */
    protected function getUserId(): int
    {
        if ($this->userId === null) {
            $user = Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }
            $this->userId = $user->id;
        }
        return $this->userId;
    }


    /**
     * Validate date range
     */
    protected function validateDateRange($startDate, $endDate): void
    {
        if ($startDate && $endDate && $startDate > $endDate) {
            throw new \InvalidArgumentException('Start date cannot be after end date');
        }
    }

    /**
     * Build cache key for report data
     */
    protected function buildCacheKey(string $reportKey, array $params): string
    {
        $paramsHash = md5(serialize($params));
        return "report:{$reportKey}:{$this->getCompanyId()}:{$paramsHash}";
    }

    /**
     * Get cached data or execute callback
     */
    protected function getCachedData(string $cacheKey, callable $callback, int $ttl = 300)
    {
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Track performance metrics
     */
    protected function trackPerformance(string $reportKey, callable $callback)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            $result = $callback();
            
            $this->logPerformance($reportKey, [
                'execution_time_ms' => (microtime(true) - $startTime) * 1000,
                'memory_used_mb' => (memory_get_usage() - $startMemory) / 1024 / 1024,
                'status' => 'success'
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logPerformance($reportKey, [
                'execution_time_ms' => (microtime(true) - $startTime) * 1000,
                'memory_used_mb' => (memory_get_usage() - $startMemory) / 1024 / 1024,
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Log performance metrics
     */
    protected function logPerformance(string $reportKey, array $metrics): void
    {
        Log::info('Report performance metrics', [
            'company_id' => $this->getCompanyId(),
            'user_id' => $this->getUserId(),
            'report_key' => $reportKey,
            'metrics' => $metrics
        ]);
    }

    /**
     * Format response data
     */
    protected function formatResponse($data, array $meta = []): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => array_merge($meta, [
                'generated_at' => now()->toISOString(),
                'company_id' => $this->getCompanyId()
            ])
        ];
    }

    /**
     * Create report run record
     */
    protected function createReportRun(string $reportKey, array $params, string $format = 'json'): ReportRun
    {
        return ReportRun::create([
            'company_id' => $this->getCompanyId(),
            'user_id' => $this->getUserId(),
            'report_key' => $reportKey,
            'params' => $params,
            'filters' => $this->extractFilters($params),
            'format' => $format,
            'status' => 'queued',
            'started_at' => now()
        ]);
    }

    /**
     * Update report run status
     */
    protected function updateReportRun(ReportRun $run, string $status, array $data = []): bool
    {
        $updateData = array_merge($data, [
            'status' => $status,
            'completed_at' => now()
        ]);

        if ($status === 'success' || $status === 'failed') {
            $updateData['completed_at'] = now();
        }

        return $run->update($updateData);
    }

    /**
     * Extract filters from parameters
     */
    protected function extractFilters(array $params): array
    {
        $filterKeys = [
            'date_from', 'date_to', 'location_ids', 'asset_ids', 
            'status', 'category', 'priority', 'assigned_to'
        ];

        return array_intersect_key($params, array_flip($filterKeys));
    }

    /**
     * Build query with common filters
     */
    protected function buildQuery($model, array $filters = [])
    {
        // Qualify column names with the model table to avoid ambiguous columns after joins
        $table = (new $model)->getTable();

        $query = $model::query()->where("{$table}.company_id", $this->getCompanyId());
        
        // Apply common filters (qualified)
        if (!empty($filters['date_from'])) {
            $query->where("{$table}.created_at", '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where("{$table}.created_at", '<=', $filters['date_to']);
        }
        
        if (!empty($filters['location_ids'])) {
            $query->whereIn("{$table}.location_id", $filters['location_ids']);
        }
        
        if (!empty($filters['asset_ids'])) {
            $query->whereIn("{$table}.asset_id", $filters['asset_ids']);
        }

        return $query;
    }

    /**
     * Apply pagination to query
     */
    protected function applyPagination($query, int $page = 1, int $pageSize = 50)
    {
        $maxPageSize = 1000;
        $pageSize = min($pageSize, $maxPageSize);
        
        return $query->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * Calculate pagination metadata
     */
    protected function getPaginationMeta($paginatedResults): array
    {
        return [
            'current_page' => $paginatedResults->currentPage(),
            'per_page' => $paginatedResults->perPage(),
            'total' => $paginatedResults->total(),
            'last_page' => $paginatedResults->lastPage(),
            'from' => $paginatedResults->firstItem(),
            'to' => $paginatedResults->lastItem(),
            'has_more_pages' => $paginatedResults->hasMorePages()
        ];
    }

    /**
     * Format currency values
     */
    protected function formatCurrency($value, string $currency = 'AED'): string
    {
        if (is_null($value)) {
            return 'N/A';
        }

        return number_format($value, 2) . ' ' . $currency;
    }

    /**
     * Format percentage values
     */
    protected function formatPercentage($value, int $decimals = 2): string
    {
        if (is_null($value)) {
            return 'N/A';
        }

        return number_format($value, $decimals) . '%';
    }

    /**
     * Calculate date range for common periods
     */
    protected function getDateRange(string $period): array
    {
        $now = now();
        
        return match($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->endOfDay()],
            'this_week' => [$now->startOfWeek(), $now->endOfWeek()],
            'last_week' => [$now->subWeek()->startOfWeek(), $now->endOfWeek()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonth()->startOfMonth(), $now->endOfMonth()],
            'this_quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'last_quarter' => [$now->subQuarter()->startOfQuarter(), $now->endOfQuarter()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            'last_year' => [$now->subYear()->startOfYear(), $now->endOfYear()],
            'ytd' => [$now->startOfYear(), $now],
            default => [null, null]
        };
    }

    /**
     * Abstract method to generate report data
     * Must be implemented by child classes
     */
    abstract public function generateReport(string $reportKey, array $params = []): array;

    /**
     * Abstract method to get available report keys
     * Must be implemented by child classes
     */
    abstract public function getAvailableReports(): array;
}
