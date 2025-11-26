<?php

namespace App\Services\AI\NLQ\Tools;

use App\Models\AssetStatus;
use App\Services\AI\NLQ\ResponseFormatter;
use Illuminate\Support\Facades\Auth;

/**
 * Base class for all tool handlers.
 * Enforces tenant scoping and provides common utilities.
 */
abstract class BaseToolHandler
{
    protected ResponseFormatter $formatter;

    public function __construct(ResponseFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * Get company ID dynamically (not cached in constructor).
     */
    protected function getCompanyId(): string
    {
        return $this->getCompanyIdInternal();
    }

    /**
     * Internal method to get company ID.
     */
    private function getCompanyIdInternal(): string
    {
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            throw new \RuntimeException('User must be authenticated with a company.');
        }
        return (string) $user->company_id;
    }


    /**
     * Validate and sanitize tool arguments.
     *
     * @param array $arguments Raw arguments from AI
     * @param array $allowedFields Whitelist of allowed fields
     * @return array Sanitized arguments
     */
    protected function sanitizeArguments(array $arguments, array $allowedFields = []): array
    {
        if (empty($allowedFields)) {
            return $arguments;
        }

        return array_intersect_key($arguments, array_flip($allowedFields));
    }

    /**
     * Validate limit parameter.
     *
     * @param mixed $limit
     * @param int $max Maximum allowed limit
     * @return int Validated limit
     */
    protected function validateLimit($limit, int $max = 500): int
    {
        if (is_null($limit)) {
            return 100; // Default limit
        }

        // Ensure limit is numeric and positive
        if (!is_numeric($limit)) {
            return 100; // Default on invalid input
        }

        $limit = (int) $limit;
        return min(max(1, $limit), $max);
    }

    /**
     * Validate and sanitize integer ID parameter.
     *
     * @param mixed $id
     * @return int|null Validated ID or null if invalid
     */
    protected function validateId($id): ?int
    {
        if (is_null($id)) {
            return null;
        }

        if (!is_numeric($id)) {
            return null;
        }

        $id = (int) $id;
        return $id > 0 ? $id : null;
    }

    /**
     * Validate and sanitize string parameter.
     *
     * @param mixed $value
     * @param int $maxLength Maximum length
     * @return string|null Sanitized string or null if invalid
     */
    protected function validateString($value, int $maxLength = 500): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return strlen($value) > 0 && strlen($value) <= $maxLength ? $value : null;
    }

    /**
     * Parse date range from arguments.
     *
     * @param array $arguments
     * @param string $startKey Key for start date (e.g., 'start_date')
     * @param string $endKey Key for end date (e.g., 'end_date')
     * @return array ['start' => Carbon|null, 'end' => Carbon|null]
     */
    protected function parseDateRange(array $arguments, string $startKey = 'start_date', string $endKey = 'end_date'): array
    {
        $start = isset($arguments[$startKey]) ? \Carbon\Carbon::parse($arguments[$startKey]) : null;
        $end = isset($arguments[$endKey]) ? \Carbon\Carbon::parse($arguments[$endKey]) : null;

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Check if user has permission to access a module.
     * Checks if user has 'can_view' permission for the module.
     *
     * @param string $module Module key (e.g., 'assets', 'work_orders')
     * @return bool
     */
    protected function hasModuleAccess(string $module): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Check if user has 'can_view' permission for this module
        return $user->hasPermission($module, 'can_view');
    }

    /**
     * Normalize status name input.
     * Handles various formats: "active", "Active", "ACTIVE", "in_transit", "In Transit", "in-transit"
     * Returns: "Active", "In Transit", etc. (proper case)
     *
     * @param string $input Status name input
     * @return string Normalized status name
     */
    protected function normalizeStatusName(string $input): string
    {
        // Handle: "active", "Active", "ACTIVE", "in_transit", "In Transit", "in-transit"
        // Return: "Active", "In Transit", etc. (proper case)
        $normalized = ucwords(str_replace(['_', '-'], ' ', strtolower(trim($input))));
        return $normalized;
    }

    /**
     * Get AssetStatus ID by name (case-insensitive).
     * Handles normalization of input and finds matching AssetStatus.
     *
     * @param string $statusName Status name (e.g., "active", "Active", "in_transit", "In Transit")
     * @return int|null AssetStatus ID or null if not found
     */
    protected function getStatusIdByName(string $statusName): ?int
    {
        $normalized = $this->normalizeStatusName($statusName);
        $status = AssetStatus::whereRaw('LOWER(name) = ?', [strtolower($normalized)])
            ->orWhereRaw('LOWER(name) = ?', [strtolower(str_replace(' ', '', $normalized))])
            ->first();
        return $status ? $status->id : null;
    }

    /**
     * Get tool definition for OpenAI function calling.
     * Must be implemented by each tool handler.
     *
     * @return array Tool definition array
     */
    abstract public function getToolDefinition(): array;

    /**
     * Execute the tool with given arguments.
     * Must be implemented by each tool handler.
     *
     * @param array $arguments Parsed arguments from AI
     * @return array Tool execution result
     */
    abstract public function execute(array $arguments): array;
}

