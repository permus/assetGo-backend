<?php

namespace App\Services;

use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderPriority;
use App\Models\WorkOrderCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MaintenanceReportService extends ReportService
{
    /**
     * Generate maintenance summary report
     */
    public function generateSummary(array $params = []): array
    {
        return $this->trackPerformance('maintenance.summary', function() use ($params) {
            $filters = $this->extractFilters($params);
            $this->validateDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);

            $query = $this->buildQuery(WorkOrder::class, $filters)
                ->with(['asset', 'location', 'status', 'priority', 'category', 'assignedTo']);

            // Apply maintenance-specific filters
            if (!empty($filters['status_id'])) {
                $query->where('status_id', $filters['status_id']);
            }

            if (!empty($filters['priority_id'])) {
                $query->where('priority_id', $filters['priority_id']);
            }

            if (!empty($filters['assigned_to'])) {
                $query->where('assigned_to', $filters['assigned_to']);
            }

            // For PDF exports, fetch all data without pagination
            $format = $params['format'] ?? 'json';
            
            if ($format === 'pdf' || !empty($params['all_data'])) {
                // Fetch all work orders for PDF export
                $workOrders = $query->get();
            } else {
                $pageSize = $params['page_size'] ?? 50;
                $workOrders = $this->applyPagination($query, $params['page'] ?? 1, $pageSize);
            }

            // Calculate KPIs
            $kpis = $this->calculateMaintenanceKPIs($filters);

            // Get status distribution
            $statusDistribution = $this->getStatusDistribution($filters);

            // Get priority distribution
            $priorityDistribution = $this->getPriorityDistribution($filters);

            // Handle both paginated and non-paginated results
            $workOrdersArray = $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? collect($workOrders->items())->map(fn($workOrder) => $workOrder->toArray())->all()
                : collect($workOrders)->map(fn($workOrder) => $workOrder->toArray())->all();

            return $this->formatResponse([
                'work_orders' => $workOrdersArray,
                'kpis' => $kpis,
                'status_distribution' => $statusDistribution,
                'priority_distribution' => $priorityDistribution,
                'pagination' => $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator 
                    ? $this->getPaginationMeta($workOrders) 
                    : null
            ]);
        });
    }

    /**
     * Generate maintenance compliance report
     */
    public function generateCompliance(array $params = []): array
    {
        return $this->trackPerformance('maintenance.compliance', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            // This would typically involve preventive maintenance schedules
            // For now, we'll provide a basic structure based on work orders
            $query = $this->buildQuery(WorkOrder::class, $filters)
                ->with(['asset', 'location', 'category']);

            // For PDF exports, fetch all data without pagination
            $format = $params['format'] ?? 'json';
            
            if ($format === 'pdf' || !empty($params['all_data'])) {
                // Fetch all work orders for PDF export
                $workOrders = $query->get();
            } else {
                $pageSize = $params['page_size'] ?? 50;
                $workOrders = $this->applyPagination($query, $params['page'] ?? 1, $pageSize);
            }

            // Calculate compliance metrics
            $workOrdersItems = $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? $workOrders->items()
                : $workOrders->all();
            $complianceData = $this->calculateComplianceMetrics($workOrdersItems);

            return $this->formatResponse([
                'work_orders' => $complianceData,
                'pagination' => $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator 
                    ? $this->getPaginationMeta($workOrders) 
                    : null
            ]);
        });
    }

    /**
     * Generate maintenance costs report
     */
    public function generateCosts(array $params = []): array
    {
        return $this->trackPerformance('maintenance.costs', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            $query = $this->buildQuery(WorkOrder::class, $filters)
                ->with(['asset', 'location', 'category']);

            // For PDF exports, fetch all data without pagination
            $format = $params['format'] ?? 'json';
            
            if ($format === 'pdf' || !empty($params['all_data'])) {
                // Fetch all work orders for PDF export
                $workOrders = $query->get();
            } else {
                $pageSize = $params['page_size'] ?? 50;
                $workOrders = $this->applyPagination($query, $params['page'] ?? 1, $pageSize);
            }

            // Calculate cost metrics
            $workOrdersItems = $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? $workOrders->items()
                : $workOrders->all();
            $costData = $this->calculateCostMetrics($workOrdersItems);

            // Get cost trends
            $costTrends = $this->getCostTrends($filters);

            return $this->formatResponse([
                'work_orders' => $costData,
                'cost_trends' => $costTrends,
                'pagination' => $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator 
                    ? $this->getPaginationMeta($workOrders) 
                    : null
            ]);
        });
    }

    /**
     * Generate downtime analysis report
     */
    public function generateDowntime(array $params = []): array
    {
        return $this->trackPerformance('maintenance.downtime', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            $query = $this->buildQuery(WorkOrder::class, $filters)
                ->with(['asset', 'location'])
                ->whereNotNull('actual_hours');

            // For PDF exports, fetch all data without pagination
            $format = $params['format'] ?? 'json';
            
            if ($format === 'pdf' || !empty($params['all_data'])) {
                // Fetch all work orders for PDF export
                $workOrders = $query->get();
            } else {
                $pageSize = $params['page_size'] ?? 50;
                $workOrders = $this->applyPagination($query, $params['page'] ?? 1, $pageSize);
            }

            // Calculate downtime metrics
            $workOrdersItems = $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? $workOrders->items()
                : $workOrders->all();
            $downtimeData = $this->calculateDowntimeMetrics($workOrdersItems);

            return $this->formatResponse([
                'work_orders' => $downtimeData,
                'pagination' => $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator 
                    ? $this->getPaginationMeta($workOrders) 
                    : null
            ]);
        });
    }

    /**
     * Generate failure analysis report
     */
    public function generateFailureAnalysis(array $params = []): array
    {
        return $this->trackPerformance('maintenance.failure_analysis', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            $query = $this->buildQuery(WorkOrder::class, $filters)
                ->with(['asset', 'location', 'category'])
                ->where('priority_id', function($q) {
                    $q->select('id')
                      ->from('work_order_priority')
                      ->whereIn('slug', ['high', 'critical']);
                });

            // For PDF exports, fetch all data without pagination
            $format = $params['format'] ?? 'json';
            
            if ($format === 'pdf' || !empty($params['all_data'])) {
                // Fetch all work orders for PDF export
                $workOrders = $query->get();
            } else {
                $pageSize = $params['page_size'] ?? 50;
                $workOrders = $this->applyPagination($query, $params['page'] ?? 1, $pageSize);
            }

            // Analyze failure patterns
            $workOrdersItems = $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? $workOrders->items()
                : $workOrders->all();
            $failureData = $this->analyzeFailurePatterns($workOrdersItems);

            return $this->formatResponse([
                'work_orders' => $failureData,
                'pagination' => $workOrders instanceof \Illuminate\Pagination\LengthAwarePaginator 
                    ? $this->getPaginationMeta($workOrders) 
                    : null
            ]);
        });
    }

    /**
     * Generate technician performance report
     */
    public function generateTechnicianPerformance(array $params = []): array
    {
        return $this->trackPerformance('maintenance.technician_performance', function() use ($params) {
            $filters = $this->extractFilters($params);
            
            // Get technicians with their work orders
            $technicians = User::where('company_id', $this->companyId)
                ->where('user_type', 'team')
                ->with(['assignedWorkOrders' => function($query) use ($filters) {
                    $this->buildQuery($query, $filters);
                }])
                ->get();

            // Calculate performance metrics for each technician
            $performanceData = $this->calculateTechnicianPerformance($technicians);

            return $this->formatResponse([
                'technicians' => $performanceData
            ]);
        });
    }

    /**
     * Calculate maintenance KPIs
     */
    private function calculateMaintenanceKPIs(array $filters): array
    {
        $query = $this->buildQuery(WorkOrder::class, $filters);

        // Total work orders
        $totalWorkOrders = $query->count();

        // Completed work orders
        $completedQuery = clone $query;
        $completedWorkOrders = $completedQuery->whereHas('status', function($q) {
            $q->where('slug', 'completed');
        })->count();

        // Overdue work orders
        $overdueQuery = clone $query;
        $overdueWorkOrders = $overdueQuery->overdue()->count();

        // Average resolution time
        $resolutionQuery = clone $query;
        $avgResolutionTime = $resolutionQuery->whereNotNull('completed_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')
            ->value('avg_hours') ?? 0;

        // MTTR (Mean Time To Repair)
        $mttrQuery = clone $query;
        $mttr = $mttrQuery->whereNotNull('actual_hours')
            ->whereNotNull('completed_at')
            ->avg('actual_hours') ?? 0;

        return [
            'total_work_orders' => $totalWorkOrders,
            'completed_work_orders' => $completedWorkOrders,
            'overdue_work_orders' => $overdueWorkOrders,
            'completion_rate' => $totalWorkOrders > 0 ? 
                round(($completedWorkOrders / $totalWorkOrders) * 100, 2) : 0,
            'overdue_rate' => $totalWorkOrders > 0 ? 
                round(($overdueWorkOrders / $totalWorkOrders) * 100, 2) : 0,
            'avg_resolution_time_hours' => round($avgResolutionTime, 2),
            'mttr_hours' => round($mttr, 2)
        ];
    }

    /**
     * Get status distribution
     */
    private function getStatusDistribution(array $filters): array
    {
        $result = $this->buildQuery(WorkOrder::class, $filters)
            ->join('work_order_status', 'work_orders.status_id', '=', 'work_order_status.id')
            ->select('work_order_status.name', DB::raw('COUNT(*) as count'))
            ->groupBy('work_order_status.id', 'work_order_status.name')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->name => $item->count];
            })
            ->toArray();

        return $result;
    }

    /**
     * Get priority distribution
     */
    private function getPriorityDistribution(array $filters): array
    {
        $result = $this->buildQuery(WorkOrder::class, $filters)
            ->join('work_order_priority', 'work_orders.priority_id', '=', 'work_order_priority.id')
            ->select('work_order_priority.name', DB::raw('COUNT(*) as count'))
            ->groupBy('work_order_priority.id', 'work_order_priority.name')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->name => $item->count];
            })
            ->toArray();

        return $result;
    }

    /**
     * Calculate compliance metrics
     */
    private function calculateComplianceMetrics(array $workOrders): array
    {
        // Placeholder implementation - would need preventive maintenance schedules
        return array_map(function ($workOrder) {
            return [
                'id' => $workOrder->id,
                'title' => $workOrder->title,
                'asset' => $workOrder->asset?->name ?? 'N/A',
                'location' => $workOrder->location?->name ?? 'N/A',
                'compliance_status' => 'compliant', // Placeholder
                'scheduled_date' => $workOrder->due_date?->toDateString(),
                'completed_date' => $workOrder->completed_at?->toDateString(),
                'days_overdue' => $workOrder->is_overdue ? $workOrder->days_until_due : 0
            ];
        }, $workOrders);
    }

    /**
     * Calculate cost metrics
     */
    private function calculateCostMetrics(array $workOrders): array
    {
        return array_map(function ($workOrder) {
            // Placeholder cost calculation - would need actual cost data
            $estimatedCost = $workOrder->estimated_hours * 50; // $50/hour placeholder
            $actualCost = $workOrder->actual_hours * 50;

            return [
                'id' => $workOrder->id,
                'title' => $workOrder->title,
                'asset' => $workOrder->asset?->name ?? 'N/A',
                'location' => $workOrder->location?->name ?? 'N/A',
                'estimated_hours' => $workOrder->estimated_hours,
                'actual_hours' => $workOrder->actual_hours,
                'estimated_cost' => $estimatedCost,
                'actual_cost' => $actualCost,
                'cost_variance' => $actualCost - $estimatedCost,
                'variance_percentage' => $estimatedCost > 0 ? 
                    round((($actualCost - $estimatedCost) / $estimatedCost) * 100, 2) : 0
            ];
        }, $workOrders);
    }

    /**
     * Get cost trends
     */
    private function getCostTrends(array $filters): array
    {
        // Placeholder implementation - would need historical cost data
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = [
                'month' => $date->format('M Y'),
                'total_cost' => rand(5000, 15000), // Placeholder
                'work_order_count' => rand(10, 30) // Placeholder
            ];
        }
        return $months;
    }

    /**
     * Calculate downtime metrics
     */
    private function calculateDowntimeMetrics(array $workOrders): array
    {
        return array_map(function ($workOrder) {
            $downtimeHours = $workOrder->actual_hours ?? 0;
            $impactLevel = 'low';
            if ($downtimeHours > 24) $impactLevel = 'high';
            elseif ($downtimeHours > 8) $impactLevel = 'medium';

            return [
                'id' => $workOrder->id,
                'title' => $workOrder->title,
                'asset' => $workOrder->asset?->name ?? 'N/A',
                'location' => $workOrder->location?->name ?? 'N/A',
                'downtime_hours' => $downtimeHours,
                'impact_level' => $impactLevel,
                'start_date' => $workOrder->created_at?->toDateString(),
                'end_date' => $workOrder->completed_at?->toDateString(),
                'priority' => $workOrder->priority?->name ?? 'N/A'
            ];
        }, $workOrders);
    }

    /**
     * Analyze failure patterns
     */
    private function analyzeFailurePatterns(array $workOrders): array
    {
        return array_map(function ($workOrder) {
            return [
                'id' => $workOrder->id,
                'title' => $workOrder->title,
                'asset' => $workOrder->asset?->name ?? 'N/A',
                'location' => $workOrder->location?->name ?? 'N/A',
                'category' => $workOrder->category?->name ?? 'N/A',
                'priority' => $workOrder->priority?->name ?? 'N/A',
                'failure_type' => 'mechanical', // Placeholder
                'root_cause' => 'Wear and tear', // Placeholder
                'prevention_action' => 'Schedule regular maintenance', // Placeholder
                'created_at' => $workOrder->created_at?->toDateString()
            ];
        }, $workOrders);
    }

    /**
     * Calculate technician performance
     */
    private function calculateTechnicianPerformance($technicians): array
    {
        return $technicians->map(function ($technician) {
            $workOrders = $technician->assignedWorkOrders;
            $completedWorkOrders = $workOrders->where('status.slug', 'completed');
            
            $totalWorkOrders = $workOrders->count();
            $completedCount = $completedWorkOrders->count();
            $avgResolutionTime = $completedWorkOrders->avg('resolution_time_days') ?? 0;
            $totalHours = $completedWorkOrders->sum('actual_hours') ?? 0;

            return [
                'id' => $technician->id,
                'name' => $technician->name,
                'email' => $technician->email,
                'total_work_orders' => $totalWorkOrders,
                'completed_work_orders' => $completedCount,
                'completion_rate' => $totalWorkOrders > 0 ? 
                    round(($completedCount / $totalWorkOrders) * 100, 2) : 0,
                'avg_resolution_time_days' => round($avgResolutionTime, 2),
                'total_hours_worked' => round($totalHours, 2),
                'efficiency_score' => $this->calculateEfficiencyScore($completedCount, $totalHours)
            ];
        })->toArray();
    }

    /**
     * Calculate efficiency score
     */
    private function calculateEfficiencyScore(int $completedCount, float $totalHours): float
    {
        if ($completedCount === 0 || $totalHours === 0) {
            return 0;
        }

        // Simple efficiency calculation: completed work orders per hour
        return round($completedCount / $totalHours, 2);
    }

    /**
     * Generate report data
     */
    public function generateReport(string $reportKey, array $params = []): array
    {
        return match($reportKey) {
            'maintenance.summary' => $this->generateSummary($params),
            'maintenance.compliance' => $this->generateCompliance($params),
            'maintenance.costs' => $this->generateCosts($params),
            'maintenance.downtime' => $this->generateDowntime($params),
            'maintenance.failure_analysis' => $this->generateFailureAnalysis($params),
            'maintenance.technician_performance' => $this->generateTechnicianPerformance($params),
            default => throw new \InvalidArgumentException("Unknown report key: {$reportKey}")
        };
    }

    /**
     * Get available maintenance reports
     */
    public function getAvailableReports(): array
    {
        return [
            'maintenance.summary' => [
                'name' => 'Maintenance Summary',
                'description' => 'Overview of maintenance activities with KPIs and distributions',
                'category' => 'maintenance'
            ],
            'maintenance.compliance' => [
                'name' => 'Maintenance Compliance',
                'description' => 'Preventive maintenance compliance rates and tracking',
                'category' => 'maintenance'
            ],
            'maintenance.costs' => [
                'name' => 'Maintenance Costs',
                'description' => 'Maintenance cost analysis and budget tracking',
                'category' => 'maintenance'
            ],
            'maintenance.downtime' => [
                'name' => 'Downtime Analysis',
                'description' => 'Asset downtime analysis and impact assessment',
                'category' => 'maintenance'
            ],
            'maintenance.failure_analysis' => [
                'name' => 'Failure Analysis',
                'description' => 'Root cause analysis and failure pattern identification',
                'category' => 'maintenance'
            ],
            'maintenance.technician_performance' => [
                'name' => 'Technician Performance',
                'description' => 'Technician productivity and performance metrics',
                'category' => 'maintenance'
            ]
        ];
    }
}
