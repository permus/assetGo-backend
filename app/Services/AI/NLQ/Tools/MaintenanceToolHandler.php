<?php

namespace App\Services\AI\NLQ\Tools;

use App\Models\WorkOrder;
use App\Services\AI\NLQ\ResponseFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tool handler for maintenance cost-related queries.
 */
class MaintenanceToolHandler extends BaseToolHandler
{
    public function getToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_maintenance_cost_summary',
                'description' => 'Get maintenance cost summary for assets or work orders. Calculates costs based on work order hours (estimated_hours or actual_hours) multiplied by hourly rate. Use this to answer questions about maintenance costs, total costs, or cost per asset.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'asset_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by specific asset ID to get costs for that asset',
                        ],
                        'location_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by location ID to get costs for assets in that location',
                        ],
                        'start_date' => [
                            'type' => 'string',
                            'description' => 'Start date for cost calculation (ISO 8601 format, e.g., 2024-01-01)',
                        ],
                        'end_date' => [
                            'type' => 'string',
                            'description' => 'End date for cost calculation (ISO 8601 format, e.g., 2024-12-31)',
                        ],
                        'hourly_rate' => [
                            'type' => 'number',
                            'description' => 'Hourly rate for cost calculation (default: 50)',
                        ],
                        'use_actual_hours' => [
                            'type' => 'boolean',
                            'description' => 'If true, use actual_hours; if false, use estimated_hours (default: true)',
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
                'message' => 'You do not have permission to access maintenance cost data.',
            ];
        }

        $companyId = $this->getCompanyId();
        $hourlyRate = isset($arguments['hourly_rate']) ? max(0, (float) $arguments['hourly_rate']) : 50.0;
        $useActualHours = $arguments['use_actual_hours'] ?? true;

        $query = WorkOrder::where('work_orders.company_id', $companyId);

        // Apply filters
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

        if (isset($arguments['start_date'])) {
            try {
                $startDate = Carbon::parse($arguments['start_date']);
                $query->where('work_orders.created_at', '>=', $startDate);
            } catch (\Exception $e) {
                // Invalid date format - ignore filter
            }
        }

        if (isset($arguments['end_date'])) {
            try {
                $endDate = Carbon::parse($arguments['end_date']);
                $query->where('work_orders.created_at', '<=', $endDate);
            } catch (\Exception $e) {
                // Invalid date format - ignore filter
            }
        }

        // Calculate costs using database aggregation
        $hoursField = $useActualHours ? 'actual_hours' : 'estimated_hours';
        
        $summary = $query->selectRaw("
            COUNT(*) as total_work_orders,
            COALESCE(SUM({$hoursField}), 0) as total_hours,
            COALESCE(SUM({$hoursField} * {$hourlyRate}), 0) as total_cost,
            COALESCE(AVG({$hoursField}), 0) as avg_hours_per_wo,
            COUNT(DISTINCT asset_id) as unique_assets
        ")->first();

        // Get cost breakdown by asset if asset_id not specified
        $costByAsset = [];
        if (!isset($arguments['asset_id'])) {
            $costByAssetQuery = clone $query;
            $costByAsset = $costByAssetQuery
                ->whereNotNull('asset_id')
                ->selectRaw("
                    asset_id,
                    COUNT(*) as work_order_count,
                    COALESCE(SUM({$hoursField}), 0) as total_hours,
                    COALESCE(SUM({$hoursField} * {$hourlyRate}), 0) as total_cost
                ")
                ->groupBy('asset_id')
                ->orderByDesc('total_cost')
                ->limit(20) // Top 20 assets by cost
                ->get()
                ->map(function ($item) {
                    return [
                        'asset_id' => $item->asset_id,
                        'work_order_count' => $item->work_order_count,
                        'total_hours' => (float) $item->total_hours,
                        'total_cost' => (float) $item->total_cost,
                    ];
                })
                ->toArray();
        }

        return [
            'total_count' => 1,
            'limited_to' => 1,
            'has_more' => false,
            'data' => [
                [
                    'summary' => [
                        'total_work_orders' => (int) ($summary->total_work_orders ?? 0),
                        'total_hours' => round((float) ($summary->total_hours ?? 0), 2),
                        'total_cost' => round((float) ($summary->total_cost ?? 0), 2),
                        'avg_hours_per_wo' => round((float) ($summary->avg_hours_per_wo ?? 0), 2),
                        'unique_assets' => (int) ($summary->unique_assets ?? 0),
                        'hourly_rate' => $hourlyRate,
                        'hours_field_used' => $hoursField,
                    ],
                    'cost_by_asset' => $costByAsset,
                ],
            ],
        ];
    }
}

