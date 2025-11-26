<?php

namespace App\Services\AI\NLQ\Tools;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\ScheduleMaintenance;
use App\Services\AI\NLQ\ResponseFormatter;
use Illuminate\Support\Facades\DB;

/**
 * Tool handler for asset-related queries.
 */
class AssetToolHandler extends BaseToolHandler
{
    public function getToolDefinition(): array
    {
        return [
            'type' => 'function',
                'function' => [
                'name' => 'get_assets',
                'description' => 'Get assets with optional filters. Use this tool to query assets by status/condition, location, category, search term, or overdue maintenance. ALWAYS use this tool when asked about asset conditions, status, overdue maintenance, or to list assets. Available status values: Active, Maintenance, Inactive, Retired, Archived, Pending, In Transit, Damaged, Lost, Disposed. Status filter accepts status names (case-insensitive, e.g., "active", "Active", "in_transit", "In Transit"). Use overdue_maintenance=true to get assets with overdue maintenance schedules. Returns asset details including id, name, status (condition name), purchase_price, location_id, and health_score. Example: Use status="active" to get all active assets, or overdue_maintenance=true to get assets with overdue maintenance, or call without filters to get all assets with their conditions.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['active', 'maintenance', 'inactive', 'archived', 'retired', 'pending', 'in_transit', 'damaged', 'lost', 'disposed'],
                            'description' => 'Filter by asset status name (case-insensitive). Accepts: active, maintenance, inactive, retired, archived, pending, in_transit (or "in transit"), damaged, lost, disposed. Examples: "active", "Active", "in_transit", "In Transit" all work.',
                        ],
                        'location_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by location ID',
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by category ID',
                        ],
                        'search' => [
                            'type' => 'string',
                            'description' => 'Search in asset name, description, serial number, or model',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 500,
                            'description' => 'Maximum number of records to return (default: 100)',
                        ],
                        'order_by' => [
                            'type' => 'string',
                            'enum' => ['name', 'purchase_price', 'purchase_date', 'created_at', 'health_score'],
                            'description' => 'Field to sort by (default: name)',
                        ],
                        'order_direction' => [
                            'type' => 'string',
                            'enum' => ['asc', 'desc'],
                            'description' => 'Sort direction (default: asc)',
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
                        'overdue_maintenance' => [
                            'type' => 'boolean',
                            'description' => 'If true, only return assets with overdue maintenance schedules. Checks both AssetMaintenanceSchedule (next_due < today) and ScheduleMaintenance (due_date < now, status != completed) systems.',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        if (!$this->hasModuleAccess('assets')) {
            return [
                'error' => true,
                'message' => 'You do not have permission to access assets.',
            ];
        }

        // Validate and sanitize inputs
        $perPage = $this->validateLimit($arguments['per_page'] ?? $arguments['limit'] ?? null);
        $page = max(1, (int) ($arguments['page'] ?? 1));
        $limit = $this->validateLimit($arguments['limit'] ?? null);
        $companyId = $this->getCompanyId();
        $query = Asset::where('company_id', $companyId);

        // Apply filters with validation
        // Handle status filtering - status column stores AssetStatus ID (integer) but may have legacy string values
        if (isset($arguments['status'])) {
            $statusInput = $this->validateString($arguments['status'], 50);
            if ($statusInput) {
                // Normalize status name using helper method
                $normalizedStatus = $this->normalizeStatusName($statusInput);
                
                // Try to find AssetStatus ID by name (case-insensitive)
                $statusId = $this->getStatusIdByName($statusInput);
                
                if ($statusId !== null) {
                    // Status is stored as ID - filter by ID (primary case)
                    $query->where('assets.status', $statusId);
                } else {
                    // Handle legacy string values: try direct match (case-insensitive)
                    // This handles old data where status might be stored as string "active" instead of ID
                    $query->where(function($q) use ($normalizedStatus) {
                        $q->whereRaw('LOWER(CAST(assets.status AS CHAR)) = ?', [strtolower($normalizedStatus)])
                          ->orWhereRaw('LOWER(CAST(assets.status AS CHAR)) = ?', [strtolower(str_replace(' ', '', $normalizedStatus))]);
                    });
                }
            }
        }

        if (isset($arguments['location_id'])) {
            $locationId = $this->validateId($arguments['location_id']);
            if ($locationId) {
                $query->where('location_id', $locationId);
            }
        }

        if (isset($arguments['category_id'])) {
            $categoryId = $this->validateId($arguments['category_id']);
            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }
        }

        if (isset($arguments['search']) && !empty($arguments['search'])) {
            $search = $this->sanitizeSearchInput($arguments['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        // Filter by overdue maintenance schedules
        if (isset($arguments['overdue_maintenance']) && $arguments['overdue_maintenance']) {
            // Get asset IDs from overdue ScheduleMaintenance records (stored as JSON array)
            $overdueScheduleAssetIds = ScheduleMaintenance::whereHas('plan', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->where('due_date', '<', now())
            ->where('status', '!=', 'completed')
            ->get()
            ->pluck('asset_ids')
            ->flatten()
            ->unique()
            ->filter()
            ->toArray();
            
            $query->where(function($q) use ($overdueScheduleAssetIds) {
                // Check AssetMaintenanceSchedule system (direct relationship)
                $q->whereHas('maintenanceSchedules', function($subQ) {
                    $subQ->where('next_due', '<', now())
                         ->where(function($statusQ) {
                             $statusQ->where('status', 'active')
                                     ->orWhereNull('status')
                                     ->orWhere('status', '!=', 'completed');
                         });
                });
                
                // OR asset ID is in overdue ScheduleMaintenance
                if (!empty($overdueScheduleAssetIds)) {
                    $q->orWhereIn('id', $overdueScheduleAssetIds);
                }
            });
        }

        // Apply sorting
        $orderBy = $arguments['order_by'] ?? 'name';
        $orderDirection = $arguments['order_direction'] ?? 'asc';
        
        // Validate order_by field
        $allowedOrderFields = ['name', 'purchase_price', 'purchase_date', 'created_at', 'health_score'];
        if (!in_array($orderBy, $allowedOrderFields, true)) {
            $orderBy = 'name';
        }
        
        // Validate order_direction
        $orderDirection = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
        
        $query->orderBy($orderBy, $orderDirection);

        // Eager load status relationship to get status names
        $query->with('assetStatus');
        
        // Format results helper function
        $formatResults = function ($results) {
            return $results->map(function ($asset) {
                // Get status name - try from relationship first, then resolve from ID/string
                $statusName = null;
                if ($asset->relationLoaded('assetStatus') && $asset->assetStatus) {
                    $statusName = $asset->assetStatus->name;
                } elseif (is_numeric($asset->status)) {
                    // If status is an ID, try to get the name
                    $statusModel = AssetStatus::find($asset->status);
                    $statusName = $statusModel ? $statusModel->name : (string) $asset->status;
                } else {
                    // Status is stored as string - use as-is or try to find matching AssetStatus
                    $statusModel = AssetStatus::whereRaw('LOWER(name) = ?', [strtolower($asset->status)])->first();
                    $statusName = $statusModel ? $statusModel->name : $asset->status;
                }
                
                return [
                    'id' => $asset->id,
                    'asset_id' => $asset->asset_id,
                    'name' => $asset->name,
                    'description' => $asset->description,
                    'status' => $statusName,
                    'location_id' => $asset->location_id,
                    'category_id' => $asset->category_id,
                    'serial_number' => $asset->serial_number,
                    'model' => $asset->model,
                    'manufacturer' => $asset->manufacturer,
                    'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
                    'purchase_price' => $asset->purchase_price ? (float) $asset->purchase_price : null,
                    'health_score' => $asset->health_score,
                ];
            });
        };

        // Handle pagination
        if (isset($arguments['page'])) {
            $offset = ($page - 1) * $perPage;
            $results = $query->offset($offset)->limit($perPage + 1)->get();
            $hasMore = $results->count() > $perPage;
            $results = $results->take($perPage);
            
            // Get total count for pagination metadata (clone query without limit/offset)
            $totalCount = (clone $query)->count();
            
            // Format results
            $formatted = $formatResults($results);
            
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

        // Non-paginated query (backward compatible)
        $results = $query->limit($limit + 1)->get(); // Fetch one extra to check if there's more
        $hasMore = $results->count() > $limit;
        $results = $results->take($limit);

        // Format results (exclude sensitive fields)
        $formatted = $formatResults($results);

        return $this->formatter->format($formatted, $limit);
    }

    /**
     * Sanitize search input to prevent SQL injection.
     * Escapes LIKE wildcards (% and _).
     */
    private function sanitizeSearchInput(string $input): string
    {
        // Escape LIKE wildcards
        return addcslashes(trim($input), '%_');
    }
}

