<?php

namespace App\Services\AI\NLQ\Tools;

use App\Models\Location;
use App\Services\AI\NLQ\ResponseFormatter;

/**
 * Tool handler for location-related queries.
 */
class LocationToolHandler extends BaseToolHandler
{
    public function getToolDefinition(): array
    {
        return [
            'type' => 'function',
                'function' => [
                'name' => 'get_locations',
                'description' => 'Get locations with optional filters. Use this to query locations by name, type, or parent location. Returns location details including id, name, location_code, description, parent_id, and hierarchy_level. Use parent_id to get child locations of a specific location.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Search in location name or description',
                        ],
                        'parent_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by parent location ID',
                        ],
                        'location_type_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by location type ID',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 500,
                            'description' => 'Maximum number of records to return (default: 100)',
                        ],
                        'order_by' => [
                            'type' => 'string',
                            'enum' => ['name', 'location_code', 'created_at', 'hierarchy_level'],
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
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        if (!$this->hasModuleAccess('locations')) {
            return [
                'error' => true,
                'message' => 'You do not have permission to access locations.',
            ];
        }

        // Validate and sanitize inputs
        $perPage = $this->validateLimit($arguments['per_page'] ?? $arguments['limit'] ?? null);
        $page = max(1, (int) ($arguments['page'] ?? 1));
        $limit = $this->validateLimit($arguments['limit'] ?? null);
        $companyId = $this->getCompanyId();
        $query = Location::where('company_id', $companyId);

        // Apply filters with validation
        if (isset($arguments['search']) && !empty($arguments['search'])) {
            $search = $this->sanitizeSearchInput($this->validateString($arguments['search'], 500) ?? '');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
        }

        if (isset($arguments['parent_id'])) {
            $parentId = $this->validateId($arguments['parent_id']);
            if ($parentId) {
                $query->where('parent_id', $parentId);
            }
        }

        if (isset($arguments['location_type_id'])) {
            $locationTypeId = $this->validateId($arguments['location_type_id']);
            if ($locationTypeId) {
                $query->where('location_type_id', $locationTypeId);
            }
        }

        // Apply sorting
        $orderBy = $arguments['order_by'] ?? 'name';
        $orderDirection = $arguments['order_direction'] ?? 'asc';
        
        // Validate order_by field
        $allowedOrderFields = ['name', 'location_code', 'created_at', 'hierarchy_level'];
        if (!in_array($orderBy, $allowedOrderFields, true)) {
            $orderBy = 'name';
        }
        
        // Validate order_direction
        $orderDirection = strtolower($orderDirection) === 'desc' ? 'desc' : 'asc';
        
        $query->orderBy($orderBy, $orderDirection);

        // Handle pagination
        if (isset($arguments['page'])) {
            $offset = ($page - 1) * $perPage;
            $results = $query->offset($offset)->limit($perPage + 1)->get();
            $hasMore = $results->count() > $perPage;
            $results = $results->take($perPage);
            
            // Get total count for pagination metadata
            $totalCount = (clone $query)->count();
        } else {
            // Non-paginated query (backward compatible)
            $results = $query->limit($limit + 1)->get();
            $hasMore = $results->count() > $limit;
            $results = $results->take($limit);
            $totalCount = null;
        }

        // Format results
        $formatted = $results->map(function ($location) {
            return [
                'id' => $location->id,
                'name' => $location->name,
                'location_code' => $location->location_code,
                'description' => $location->description,
                'parent_id' => $location->parent_id,
                'location_type_id' => $location->location_type_id,
                'address' => $location->address,
                'hierarchy_level' => $location->hierarchy_level,
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

