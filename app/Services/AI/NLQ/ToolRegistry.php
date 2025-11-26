<?php

namespace App\Services\AI\NLQ;

use App\Services\AI\NLQ\Tools\BaseToolHandler;
use App\Services\AI\NLQ\Tools\AssetToolHandler;
use App\Services\AI\NLQ\Tools\WorkOrderToolHandler;
use App\Services\AI\NLQ\Tools\LocationToolHandler;
use App\Services\AI\NLQ\Tools\MaintenanceToolHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Registry for AI tools/functions.
 * Maps tool names to handler classes and provides tool definitions for OpenAI.
 */
class ToolRegistry
{
    private ResponseFormatter $formatter;
    private array $handlers = [];

    public function __construct(ResponseFormatter $formatter)
    {
        $this->formatter = $formatter;
        $this->registerDefaultTools();
    }

    /**
     * Register default tool handlers.
     */
    private function registerDefaultTools(): void
    {
        $this->register(new AssetToolHandler($this->formatter));
        $this->register(new WorkOrderToolHandler($this->formatter));
        $this->register(new LocationToolHandler($this->formatter));
        $this->register(new MaintenanceToolHandler($this->formatter));
    }

    /**
     * Register a tool handler.
     *
     * @param BaseToolHandler $handler
     */
    public function register(BaseToolHandler $handler): void
    {
        $definition = $handler->getToolDefinition();
        $toolName = $definition['function']['name'] ?? null;

        if (!$toolName) {
            throw new \InvalidArgumentException('Tool handler must provide a valid tool name.');
        }

        $this->handlers[$toolName] = $handler;
    }

    /**
     * Get all tool definitions for OpenAI function calling.
     *
     * @return array Array of tool definitions
     */
    public function getToolDefinitions(): array
    {
        $definitions = [];

        foreach ($this->handlers as $handler) {
            $definitions[] = $handler->getToolDefinition();
        }

        return $definitions;
    }

    /**
     * Execute a tool by name with given arguments.
     *
     * @param string $toolName Name of the tool
     * @param array $arguments Arguments from AI
     * @return array Tool execution result
     */
    public function executeTool(string $toolName, array $arguments): array
    {
        if (!isset($this->handlers[$toolName])) {
            throw new \InvalidArgumentException("Unknown tool: {$toolName}");
        }

        // Validate arguments before execution
        if (!$this->validateArguments($toolName, $arguments)) {
            return [
                'error' => true,
                'message' => "Invalid arguments provided for tool {$toolName}",
            ];
        }

        $handler = $this->handlers[$toolName];

        try {
            $startTime = microtime(true);
            $requestId = uniqid('tool_', true);
            
            // Generate cache key for identical queries (30 second cache)
            $cacheKey = 'nlq_tool_' . $toolName . '_' . md5(json_encode($arguments) . '_' . \Illuminate\Support\Facades\Auth::id());
            $cachedResult = Cache::get($cacheKey);
            
            if ($cachedResult !== null) {
                Log::info('Tool result from cache', [
                    'request_id' => $requestId,
                    'tool' => $toolName,
                ]);
                return $cachedResult;
            }
            
            Log::info('Executing tool', [
                'request_id' => $requestId,
                'tool' => $toolName,
                'arguments' => $this->sanitizeLogData($arguments),
                'user_id' => \Illuminate\Support\Facades\Auth::id(),
            ]);

            // Set execution timeout (10 seconds)
            $timeout = 10;
            $originalTimeLimit = ini_get('max_execution_time');
            set_time_limit($timeout);

            try {
                $result = $handler->execute($arguments);
                
                // Cache result for 30 seconds
                Cache::put($cacheKey, $result, 30);
            } finally {
                // Restore original time limit
                if ($originalTimeLimit !== false) {
                    set_time_limit((int) $originalTimeLimit);
                }
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Tool executed successfully', [
                'request_id' => $requestId,
                'tool' => $toolName,
                'result_size' => isset($result['data']) ? count($result['data']) : 0,
                'execution_time_ms' => $executionTime,
            ]);

            return $result;
        } catch (\Exception $e) {
            $executionTime = isset($startTime) ? round((microtime(true) - $startTime) * 1000, 2) : 0;
            
            // Check if timeout occurred
            if (str_contains($e->getMessage(), 'Maximum execution time') || $executionTime >= 10000) {
                Log::error('Tool execution timeout', [
                    'request_id' => $requestId ?? null,
                    'tool' => $toolName,
                    'execution_time_ms' => $executionTime,
                ]);
                
                return [
                    'error' => true,
                    'message' => "Tool execution timed out after {$timeout} seconds",
                ];
            }

            Log::error('Tool execution failed', [
                'request_id' => $requestId ?? null,
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime,
            ]);

            return [
                'error' => true,
                'message' => "Failed to execute tool {$toolName}: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate tool arguments against tool definition.
     *
     * @param string $toolName
     * @param array $arguments
     * @return bool
     */
    public function validateArguments(string $toolName, array $arguments): bool
    {
        if (!isset($this->handlers[$toolName])) {
            return false;
        }

        $handler = $this->handlers[$toolName];
        $definition = $handler->getToolDefinition();
        $properties = $definition['function']['parameters']['properties'] ?? [];

        // Validate each argument against its definition
        foreach ($arguments as $key => $value) {
            if (!isset($properties[$key])) {
                // Unknown parameter - reject
                Log::warning('Unknown parameter in tool call', [
                    'tool' => $toolName,
                    'parameter' => $key,
                ]);
                return false;
            }

            $paramDef = $properties[$key];
            $expectedType = $paramDef['type'] ?? null;

            // Type validation
            if ($expectedType) {
                $isValid = match ($expectedType) {
                    'string' => is_string($value),
                    'integer' => is_int($value) || (is_string($value) && ctype_digit($value)),
                    'boolean' => is_bool($value),
                    'array' => is_array($value),
                    default => true, // Unknown types pass
                };

                if (!$isValid) {
                    Log::warning('Invalid parameter type in tool call', [
                        'tool' => $toolName,
                        'parameter' => $key,
                        'expected' => $expectedType,
                        'got' => gettype($value),
                    ]);
                    return false;
                }
            }

            // Enum validation
            if (isset($paramDef['enum'])) {
                // For status parameters, allow case-insensitive matching and normalization
                // This handles variations like "active", "Active", "in_transit", "In Transit"
                if ($key === 'status' && is_string($value)) {
                    // Normalize the input value
                    $normalizedValue = strtolower(str_replace(['_', '-', ' '], '', $value));
                    $enumLower = array_map(function($e) {
                        return strtolower(str_replace(['_', '-', ' '], '', $e));
                    }, $paramDef['enum']);
                    
                    if (!in_array($normalizedValue, $enumLower, true)) {
                        Log::warning('Invalid enum value in tool call', [
                            'tool' => $toolName,
                            'parameter' => $key,
                            'value' => $value,
                            'normalized' => $normalizedValue,
                            'allowed' => $paramDef['enum'],
                        ]);
                        return false;
                    }
                } elseif (!in_array($value, $paramDef['enum'], true)) {
                    Log::warning('Invalid enum value in tool call', [
                        'tool' => $toolName,
                        'parameter' => $key,
                        'value' => $value,
                        'allowed' => $paramDef['enum'],
                    ]);
                    return false;
                }
            }

            // Range validation for integers
            if ($expectedType === 'integer' && is_numeric($value)) {
                $intValue = (int) $value;
                if (isset($paramDef['minimum']) && $intValue < $paramDef['minimum']) {
                    return false;
                }
                if (isset($paramDef['maximum']) && $intValue > $paramDef['maximum']) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Sanitize data for logging (remove sensitive fields).
     *
     * @param array $data
     * @return array
     */
    private function sanitizeLogData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'api_key', 'secret'];
        $sanitized = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '***REDACTED***';
            }
        }

        return $sanitized;
    }
}

