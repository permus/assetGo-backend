<?php

namespace App\Services\AI\NLQ;

/**
 * Formats and limits database query results for AI consumption.
 * Enforces max row limits and aggregates large datasets.
 */
class ResponseFormatter
{
    private int $defaultLimit;
    private int $maxLimit;

    public function __construct(int $defaultLimit = 100, int $maxLimit = 500)
    {
        $this->defaultLimit = $defaultLimit;
        $this->maxLimit = $maxLimit;
    }

    /**
     * Format query results with limits and aggregation.
     *
     * @param \Illuminate\Support\Collection|array $results Query results
     * @param int|null $requestedLimit Limit requested by user/tool
     * @param string|null $groupBy Field to group by for aggregation
     * @return array Formatted response with metadata
     */
    public function format($results, ?int $requestedLimit = null, ?string $groupBy = null): array
    {
        $limit = min($requestedLimit ?? $this->defaultLimit, $this->maxLimit);
        
        // Convert to collection if array
        $collection = is_array($results) ? collect($results) : $results;
        $totalCount = $collection->count();

        // If results exceed limit, aggregate
        if ($totalCount > $limit) {
            return $this->aggregate($collection, $limit, $groupBy);
        }

        // Return full results with metadata
        return [
            'total_count' => $totalCount,
            'limited_to' => $totalCount,
            'has_more' => false,
            'data' => $collection->take($limit)->values()->toArray(),
        ];
    }

    /**
     * Aggregate large datasets into summaries.
     * Note: This performs in-memory aggregation. For better performance with very large datasets,
     * consider using database-level aggregation in the tool handlers.
     *
     * @param \Illuminate\Support\Collection $collection
     * @param int $limit
     * @param string|null $groupBy
     * @return array
     */
    private function aggregate($collection, int $limit, ?string $groupBy): array
    {
        $result = [
            'total_count' => $collection->count(),
            'limited_to' => $limit,
            'has_more' => true,
            'data' => $collection->take($limit)->values()->toArray(),
        ];

        // Add grouped summary if groupBy is specified
        if ($groupBy && $collection->isNotEmpty()) {
            $grouped = $collection->groupBy($groupBy)->map(function ($group) {
                return $group->count();
            });

            $result['summary'] = [
                'grouped_by' => $groupBy,
                'counts' => $grouped->toArray(),
            ];
        }

        // Add sample records (first and last)
        $sampleSize = min(10, (int) ($limit / 10));
        if ($collection->count() > $limit) {
            $result['sample'] = [
                'first' => $collection->take($sampleSize)->values()->toArray(),
                'last' => $collection->take(-$sampleSize)->values()->toArray(),
            ];
        }

        return $result;
    }

    /**
     * Format results with database-level aggregation for better performance.
     * This method should be called from tool handlers when dealing with very large datasets.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $limit
     * @param string|null $groupBy Field to group by for aggregation
     * @param array $aggregateFields Fields to aggregate (e.g., ['sum' => 'purchase_price', 'avg' => 'health_score'])
     * @return array
     */
    public function formatWithDbAggregation($query, int $limit, ?string $groupBy = null, array $aggregateFields = []): array
    {
        $totalCount = (clone $query)->count();
        
        if ($totalCount <= $limit) {
            // Small dataset - return normally
            return $this->format($query->limit($limit)->get(), $limit);
        }

        // Large dataset - return aggregated summary
        $result = [
            'total_count' => $totalCount,
            'limited_to' => $limit,
            'has_more' => true,
            'data' => $query->limit($limit)->get()->toArray(),
        ];

        // Add database-level aggregation if groupBy specified
        if ($groupBy) {
            $aggregated = (clone $query)
                ->selectRaw("{$groupBy}, COUNT(*) as count")
                ->groupBy($groupBy)
                ->get()
                ->pluck('count', $groupBy)
                ->toArray();

            $result['summary'] = [
                'grouped_by' => $groupBy,
                'counts' => $aggregated,
            ];
        }

        // Add aggregate fields (sum, avg, etc.)
        if (!empty($aggregateFields)) {
            $aggregates = [];
            foreach ($aggregateFields as $operation => $field) {
                $aggregates[$operation . '_' . $field] = (clone $query)->{$operation}($field);
            }
            $result['aggregates'] = $aggregates;
        }

        return $result;
    }

    /**
     * Format aggregated summary data (counts, totals, averages).
     *
     * @param array $summaryData Summary statistics
     * @return array Formatted summary
     */
    public function formatSummary(array $summaryData): array
    {
        return [
            'total_count' => $summaryData['total_count'] ?? 0,
            'summary' => $summaryData,
        ];
    }

    /**
     * Format single record result.
     *
     * @param mixed $record Single record
     * @return array Formatted response
     */
    public function formatSingle($record): array
    {
        if (is_null($record)) {
            return [
                'total_count' => 0,
                'limited_to' => 0,
                'has_more' => false,
                'data' => [],
            ];
        }

        return [
            'total_count' => 1,
            'limited_to' => 1,
            'has_more' => false,
            'data' => [is_array($record) ? $record : $record->toArray()],
        ];
    }
}

