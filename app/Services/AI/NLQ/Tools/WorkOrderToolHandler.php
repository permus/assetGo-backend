<?php

namespace App\Services\AI\NLQ\Tools;

use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderPriority;
use App\Services\AI\NLQ\ResponseFormatter;
use Carbon\Carbon;

/**
 * Tool handler for work order-related queries.
 */
class WorkOrderToolHandler extends BaseToolHandler
{
    public function getToolDefinition(): array
    {
        return [
            'type' => 'function',
                'function' => [
                'name' => 'get_work_orders',
                'description' => 'Get work orders with optional filters. Use this to query work orders by status, priority, assigned user, due date, asset, or location. Returns work order details including id, title, status, priority, due_date, estimated_hours, actual_hours, and asset_id. Use overdue_only=true to get only overdue work orders. Example: Use asset_id to get all work orders for a specific asset to calculate maintenance costs.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'description' => 'Filter by status slug (e.g., "open", "in-progress", "completed", "on-hold", "cancelled")',
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Filter by priority slug (e.g., "low", "medium", "high", "critical")',
                        ],
                        'assigned_to' => [
                            'type' => 'integer',
                            'description' => 'Filter by assigned user ID',
                        ],
                        'asset_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by asset ID',
                        ],
                        'location_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by location ID',
                        ],
                        'overdue_only' => [
                            'type' => 'boolean',
                            'description' => 'If true, only return overdue work orders',
                        ],
                        'due_date_start' => [
                            'type' => 'string',
                            'description' => 'Filter by due date start (ISO 8601 format)',
                        ],
                        'due_date_end' => [
                            'type' => 'string',
                            'description' => 'Filter by due date end (ISO 8601 format)',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 500,
                            'description' => 'Maximum number of records to return (default: 100)',
                        ],
                        'order_by' => [
                            'type' => 'string',
                            'enum' => ['created_at', 'due_date', 'title', 'priority', 'status'],
                            'description' => 'Field to sort by (default: created_at)',
                        ],
                        'order_direction' => [
                            'type' => 'string',
                            'enum' => ['asc', 'desc'],
                            'description' => 'Sort direction (default: desc)',
                        ],
                        'page' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'description' => 'Page number for pagination (default: 1)',
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 500,
                            'description' => 'Number of records per page (default: same as limit)',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        if (!$this->hasModuleAccess('work_orders')) {
            return [
                'error' => true,
                'message' => 'You do not have permission to access work orders.',
            ];
        }

        // Validate and sanitize inputs
        $perPage = $this->validateLimit($arguments['per_page'] ?? $arguments['limit'] ?? null);
        $page = max(1, (int) ($arguments['page'] ?? 1));
        $limit = $this->validateLimit($arguments['limit'] ?? null);
        $companyId = $this->getCompanyId();
        $query = WorkOrder::where('work_orders.company_id', $companyId);

        // Join status table if needed for filtering
        $needsStatusJoin = isset($arguments['status']) || (isset($arguments['overdue_only']) && $arguments['overdue_only']);
        if ($needsStatusJoin) {
            $query->leftJoin('work_order_status', 'work_orders.status_id', '=', 'work_order_status.id');
        }

        // Join priority table if needed
        if (isset($arguments['priority'])) {
            $query->leftJoin('work_order_priority', 'work_orders.priority_id', '=', 'work_order_priority.id');
        }

        // Apply filters with validation
        if (isset($arguments['status'])) {
            $statusSlug = $this->validateString($arguments['status'], 50);
            if ($statusSlug) {
                $status = WorkOrderStatus::where('slug', $statusSlug)
                    ->where(function($q) use ($companyId) {
                        $q->whereNull('company_id')->orWhere('company_id', $companyId);
                    })
                    ->first();
                if ($status) {
                    $query->where('work_orders.status_id', $status->id);
                }
            }
        }

        if (isset($arguments['priority'])) {
            $prioritySlug = $this->validateString($arguments['priority'], 50);
            if ($prioritySlug) {
                $priority = WorkOrderPriority::where('slug', $prioritySlug)
                    ->where(function($q) use ($companyId) {
                        $q->whereNull('company_id')->orWhere('company_id', $companyId);
                    })
                    ->first();
                if ($priority) {
                    $query->where('work_orders.priority_id', $priority->id);
                }
            }
        }

        if (isset($arguments['assigned_to'])) {
            $assignedTo = $this->validateId($arguments['assigned_to']);
            if ($assignedTo) {
                $query->where('work_orders.assigned_to', $assignedTo);
            }
        }

        if (isset($arguments['asset_id'])) {
            $assetId = $this->validateId($arguments['asset_id']);
            if ($assetId) {
                $query->where('work_orders.asset_id', $assetId);
            }
        }

        if (isset($arguments['location_id'])) {
            $locationId = $this->validateId($arguments['location_id']);
            if ($locationId) {
                $query->where('work_orders.location_id', $locationId);
            }
        }

        if (isset($arguments['overdue_only']) && $arguments['overdue_only']) {
            $query->where('work_orders.due_date', '<', Carbon::now())
                ->whereNotIn('work_order_status.slug', ['completed', 'cancelled']);
        }

        if (isset($arguments['due_date_start'])) {
            try {
                $startDate = Carbon::parse($arguments['due_date_start']);
                $query->where('work_orders.due_date', '>=', $startDate);
            } catch (\Exception $e) {
                // Invalid date format - ignore filter
            }
        }

        if (isset($arguments['due_date_end'])) {
            try {
                $endDate = Carbon::parse($arguments['due_date_end']);
                $query->where('work_orders.due_date', '<=', $endDate);
            } catch (\Exception $e) {
                // Invalid date format - ignore filter
            }
        }

        // Apply sorting
        $orderBy = $arguments['order_by'] ?? 'created_at';
        $orderDirection = $arguments['order_direction'] ?? 'desc';
        
        // Validate order_by field
        $allowedOrderFields = ['created_at', 'due_date', 'title', 'priority', 'status'];
        if (!in_array($orderBy, $allowedOrderFields, true)) {
            $orderBy = 'created_at';
        }
        
        // Validate order_direction
        $orderDirection = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
        
        // Handle special sorting cases
        if ($orderBy === 'priority') {
            // For priority, join priority table if not already joined
            if (!isset($arguments['priority'])) {
                $query->leftJoin('work_order_priority', 'work_orders.priority_id', '=', 'work_order_priority.id');
            }
            $query->orderBy('work_order_priority.slug', $orderDirection);
        } elseif ($orderBy === 'status') {
            // For status, join status table if not already joined
            if (!isset($arguments['status']) && !isset($arguments['overdue_only'])) {
                $query->leftJoin('work_order_status', 'work_orders.status_id', '=', 'work_order_status.id');
            }
            $query->orderBy('work_order_status.slug', $orderDirection);
        } else {
            $query->orderBy("work_orders.{$orderBy}", $orderDirection);
        }

        // Handle pagination
        if (isset($arguments['page'])) {
            $offset = ($page - 1) * $perPage;
            $results = $query->with(['status', 'priority'])
                ->offset($offset)
                ->limit($perPage + 1)
                ->get();
            $hasMore = $results->count() > $perPage;
            $results = $results->take($perPage);
            
            // Get total count for pagination metadata
            $totalCount = (clone $query)->count();
        } else {
            // Non-paginated query (backward compatible)
            $results = $query->with(['status', 'priority'])
                ->limit($limit + 1)
                ->get();
            $hasMore = $results->count() > $limit;
            $results = $results->take($limit);
            $totalCount = null;
        }

        // Format results - relationships are already loaded via with()
        $formatted = $results->map(function ($wo) {
            return [
                'id' => $wo->id,
                'title' => $wo->title,
                'description' => $wo->description,
                'status' => $wo->status?->slug ?? null,
                'priority' => $wo->priority?->slug ?? null,
                'due_date' => $wo->due_date?->format('Y-m-d H:i:s'),
                'completed_at' => $wo->completed_at?->format('Y-m-d H:i:s'),
                'asset_id' => $wo->asset_id,
                'location_id' => $wo->location_id,
                'assigned_to' => $wo->assigned_to,
                'estimated_hours' => $wo->estimated_hours,
                'actual_hours' => $wo->actual_hours,
                'is_overdue' => $wo->is_overdue ?? false,
            ];
        });

        // Return paginated response if pagination was used
        if (isset($arguments['page'])) {
            return [
                'total_count' => $totalCount,
                'limited_to' => $perPage,
                'has_more' => $hasMore,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage),
                'data' => $formatted->toArray(),
            ];
        }

        return $this->formatter->format($formatted, $limit);
    }
}

