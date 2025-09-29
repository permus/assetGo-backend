<?php

namespace App\Services;

use App\Models\ReportRun;
use App\Models\ReportTemplate;
use App\Models\ReportSchedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class ReportExportService
{
    /**
     * Get export statistics for a company
     */
    public function getExportStatistics(int $companyId): array
    {
        $totalExports = ReportRun::forCompany($companyId)->count();
        $successfulExports = ReportRun::forCompany($companyId)->successful()->count();
        $failedExports = ReportRun::forCompany($companyId)->failed()->count();
        
        $successRate = $totalExports > 0 ? round(($successfulExports / $totalExports) * 100, 2) : 0;
        
        // Get format distribution
        $formatDistribution = ReportRun::forCompany($companyId)
            ->selectRaw('format, COUNT(*) as count')
            ->groupBy('format')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->format => $item->count];
            })
            ->toArray();
        
        // Get recent exports
        $recentExports = ReportRun::forCompany($companyId)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($run) {
                return [
                    'id' => $run->id,
                    'report_key' => $run->report_key,
                    'format' => $run->format,
                    'status' => $run->status,
                    'status_label' => $run->status_label,
                    'row_count' => $run->row_count,
                    'created_at' => $run->created_at,
                    'user' => $run->user?->name ?? 'Unknown'
                ];
            });

        return [
            'total_exports' => $totalExports,
            'successful_exports' => $successfulExports,
            'failed_exports' => $failedExports,
            'success_rate' => $successRate,
            'format_distribution' => $formatDistribution,
            'recent_exports' => $recentExports
        ];
    }

    /**
     * Clean up old export files
     */
    public function cleanupOldFiles(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        $oldRuns = ReportRun::where('created_at', '<', $cutoffDate)
            ->whereNotNull('file_path')
            ->get();

        $deletedCount = 0;

        foreach ($oldRuns as $run) {
            try {
                if (Storage::disk('local')->exists($run->file_path)) {
                    Storage::disk('local')->delete($run->file_path);
                    $deletedCount++;
                }
                
                // Clear file path from database
                $run->update(['file_path' => null]);
                
            } catch (Exception $e) {
                Log::warning('Failed to delete old export file', [
                    'run_id' => $run->id,
                    'file_path' => $run->file_path,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Cleaned up old export files', [
            'deleted_count' => $deletedCount,
            'days_old' => $daysOld
        ]);

        return $deletedCount;
    }

    /**
     * Get storage usage statistics
     */
    public function getStorageUsage(int $companyId): array
    {
        $runs = ReportRun::forCompany($companyId)
            ->whereNotNull('file_path')
            ->get();

        $totalSize = 0;
        $fileCount = 0;
        $formatSizes = [];

        foreach ($runs as $run) {
            try {
                if (Storage::disk('local')->exists($run->file_path)) {
                    $size = Storage::disk('local')->size($run->file_path);
                    $totalSize += $size;
                    $fileCount++;
                    
                    $format = $run->format;
                    $formatSizes[$format] = ($formatSizes[$format] ?? 0) + $size;
                }
            } catch (Exception $e) {
                Log::warning('Failed to get file size', [
                    'run_id' => $run->id,
                    'file_path' => $run->file_path,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'file_count' => $fileCount,
            'format_sizes' => array_map(function ($size) {
                return round($size / 1024 / 1024, 2);
            }, $formatSizes)
        ];
    }

    /**
     * Validate export request
     */
    public function validateExportRequest(array $data): array
    {
        $errors = [];

        // Validate report key
        if (empty($data['report_key'])) {
            $errors[] = 'Report key is required';
        } elseif (!$this->isValidReportKey($data['report_key'])) {
            $errors[] = 'Invalid report key';
        }

        // Validate format
        if (empty($data['format'])) {
            $errors[] = 'Export format is required';
        } elseif (!in_array($data['format'], ['pdf', 'xlsx', 'csv', 'json'])) {
            $errors[] = 'Invalid export format';
        }

        // Validate parameters
        if (isset($data['params']) && !is_array($data['params'])) {
            $errors[] = 'Parameters must be an array';
        }

        return $errors;
    }

    /**
     * Check if report key is valid
     */
    private function isValidReportKey(string $reportKey): bool
    {
        $validKeys = [
            'assets.asset-summary',
            'assets.asset-utilization',
            'assets.depreciation-analysis',
            'assets.warranty-status',
            'assets.compliance-report',
            'assets.summary',
            'assets.utilization',
            'assets.depreciation',
            'assets.warranty',
            'assets.compliance',
            'maintenance.summary',
            'maintenance.compliance',
            'maintenance.costs',
            'maintenance.downtime',
            'maintenance.failure_analysis',
            'maintenance.technician_performance'
        ];

        return in_array($reportKey, $validKeys);
    }

    /**
     * Get export queue status
     */
    public function getQueueStatus(): array
    {
        $queuedCount = ReportRun::where('status', 'queued')->count();
        $runningCount = ReportRun::where('status', 'running')->count();
        
        return [
            'queued' => $queuedCount,
            'running' => $runningCount,
            'total_pending' => $queuedCount + $runningCount
        ];
    }

    /**
     * Get export performance metrics
     */
    public function getPerformanceMetrics(int $companyId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $runs = ReportRun::forCompany($companyId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('execution_time_ms')
            ->get();

        if ($runs->isEmpty()) {
            return [
                'avg_execution_time_ms' => 0,
                'avg_execution_time_seconds' => 0,
                'fastest_execution_ms' => 0,
                'slowest_execution_ms' => 0,
                'total_runs' => 0
            ];
        }

        $executionTimes = $runs->pluck('execution_time_ms')->filter();
        
        return [
            'avg_execution_time_ms' => round($executionTimes->avg(), 2),
            'avg_execution_time_seconds' => round($executionTimes->avg() / 1000, 2),
            'fastest_execution_ms' => $executionTimes->min(),
            'slowest_execution_ms' => $executionTimes->max(),
            'total_runs' => $runs->count()
        ];
    }
}
