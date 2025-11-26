<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\WorkOrder;
use App\Models\Location;
use App\Models\Company;
use App\Services\AI\OpenAIClient;
use App\Services\AI\NLQ\ToolRegistry;
use App\Services\AI\NLQ\ResponseFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class NaturalLanguageService
{
    private OpenAIClient $openAIClient;
    private ToolRegistry $toolRegistry;
    private ResponseFormatter $formatter;

    public function __construct(
        OpenAIClient $openAIClient,
        ToolRegistry $toolRegistry,
        ResponseFormatter $formatter
    ) {
        $this->openAIClient = $openAIClient;
        $this->toolRegistry = $toolRegistry;
        $this->formatter = $formatter;
    }

    /**
     * Get asset context for natural language queries.
     * This is kept for backward compatibility but simplified.
     */
    public function getAssetContext(string $companyId): array
    {
        return Cache::remember("nlq-context-{$companyId}", 300, function () use ($companyId) {
            // Get asset counts
            // Note: status column stores asset_status ID (integer) - status = 1 means "Active", status = 2 means "Maintenance", etc.
            $totalAssets = Asset::where('company_id', $companyId)->count();
            $totalValue = Asset::where('company_id', $companyId)->sum('purchase_price');
            
            // Count active assets - status column stores AssetStatus ID, join to check name
            // This matches the approach used in AssetController::statistics()
            $activeAssets = Asset::where('assets.company_id', $companyId)
                ->join('asset_statuses', 'assets.status', '=', 'asset_statuses.id')
                ->where('asset_statuses.name', 'Active')
                ->distinct()
                ->count('assets.id');
            
            // Count maintenance assets - status column stores AssetStatus ID, join to check name
            $maintenanceAssets = Asset::where('assets.company_id', $companyId)
                ->join('asset_statuses', 'assets.status', '=', 'asset_statuses.id')
                ->where('asset_statuses.name', 'Maintenance')
                ->distinct()
                ->count('assets.id');
            
            $assetCounts = (object) [
                'total_assets' => $totalAssets,
                'active_assets' => $activeAssets,
                'maintenance_assets' => $maintenanceAssets,
                'total_value' => $totalValue ?? 0,
            ];

            // Get work order counts
            $workOrderCounts = WorkOrder::where('work_orders.company_id', $companyId)
                ->leftJoin('work_order_status', 'work_orders.status_id', '=', 'work_order_status.id')
                ->leftJoin('work_order_priority', 'work_orders.priority_id', '=', 'work_order_priority.id')
                ->selectRaw('
                    COUNT(*) as open_work_orders,
                    COUNT(CASE WHEN work_order_priority.slug = "high" OR work_order_priority.slug = "critical" THEN 1 END) as high_priority_work_orders,
                    COUNT(CASE WHEN work_orders.due_date < NOW() AND work_order_status.slug NOT IN ("completed", "cancelled") THEN 1 END) as overdue_work_orders
                ')
                ->first();

            // Get maintenance counts (simplified - no maintenance history table exists)
            $maintenanceCounts = 0;

            // Get location count
            $locationCount = Location::where('company_id', $companyId)->count();

            // Get recent assets (max 5) with status names - handle both ID and legacy string values
            $recentAssets = Asset::where('assets.company_id', $companyId)
                ->with('assetStatus')
                ->select('assets.id', 'assets.name', 'assets.status')
                ->latest('assets.created_at')
                ->limit(5)
                ->get()
                ->map(function($asset) {
                    // Get status name - try from relationship first, then resolve from ID/string
                    $statusName = null;
                    if ($asset->relationLoaded('assetStatus') && $asset->assetStatus) {
                        $statusName = $asset->assetStatus->name;
                    } elseif (is_numeric($asset->status)) {
                        // If status is an ID, try to get the name
                        $statusModel = \App\Models\AssetStatus::find($asset->status);
                        $statusName = $statusModel ? $statusModel->name : 'Unknown';
                    } else {
                        // Status is stored as string - use as-is or try to find matching AssetStatus
                        $statusModel = \App\Models\AssetStatus::whereRaw('LOWER(name) = ?', [strtolower($asset->status)])->first();
                        $statusName = $statusModel ? $statusModel->name : ucfirst($asset->status);
                    }
                    
                    return [
                        'id' => $asset->id,
                        'name' => $asset->name,
                        'status' => $statusName ?? 'Unknown'
                    ];
                })
                ->toArray();

            // Get recent work orders (max 5)
            $recentWorkOrders = WorkOrder::where('work_orders.company_id', $companyId)
                ->leftJoin('work_order_status', 'work_orders.status_id', '=', 'work_order_status.id')
                ->select('work_orders.id', 'work_orders.title', 'work_order_status.name as status')
                ->latest('work_orders.created_at')
                ->limit(5)
                ->get()
                ->map(function($wo) {
                    return [
                        'id' => $wo->id,
                        'title' => $wo->title,
                        'status' => $wo->status ?? 'Unknown'
                    ];
                })
                ->toArray();

            return [
                'totalAssets' => $assetCounts->total_assets ?? 0,
                'activeAssets' => $assetCounts->active_assets ?? 0,
                'maintenanceAssets' => $assetCounts->maintenance_assets ?? 0,
                'totalValue' => $assetCounts->total_value ?? 0,
                'openWorkOrders' => $workOrderCounts->open_work_orders ?? 0,
                'highPriorityWorkOrders' => $workOrderCounts->high_priority_work_orders ?? 0,
                'overdueWorkOrders' => $workOrderCounts->overdue_work_orders ?? 0,
                'overdueMaintenance' => $maintenanceCounts,
                'totalLocations' => $locationCount,
                'recentAssets' => $recentAssets,
                'recentWorkOrders' => $recentWorkOrders
            ];
        });
    }

    /**
     * Get company context (name, etc.) for natural language queries.
     * 
     * @param string $companyId Company ID
     * @return array Company context array
     */
    private function getCompanyContext(string $companyId): array
    {
        return Cache::remember("nlq-company-context-{$companyId}", 3600, function () use ($companyId) {
            $company = Company::find($companyId);
            return [
                'name' => $company->name ?? 'your company'
            ];
        });
    }

    /**
     * Process natural language chat query with function calling support.
     * 
     * @param array $messages Chat messages
     * @param array $assetContext Optional context (auto-fetched if null/empty)
     * @param array $companyContext Optional company context (auto-fetched if null/empty)
     * @param string $companyId Company ID for tenant scoping
     */
    public function processChatQuery(array $messages, array $assetContext = null, array $companyContext = null, string $companyId = ''): array
    {
        try {
            // Get company ID from authenticated user if not provided
            if (empty($companyId)) {
                $user = Auth::user();
                if (!$user || !$user->company_id) {
                    throw new \RuntimeException('User must be authenticated with a company.');
                }
                $companyId = (string) $user->company_id;
            }

            // Check if the latest user message is a simple greeting (no tools or context needed)
            $latestUserMessage = $this->getLatestUserMessage($messages);
            $isSimpleGreeting = $this->isSimpleGreeting($latestUserMessage);
            
            // For simple greetings, skip fetching context to save database queries
            if (!$isSimpleGreeting) {
                // Auto-fetch assetContext if not provided (null or empty array)
                if ($assetContext === null || empty($assetContext)) {
                    $assetContext = $this->getAssetContext($companyId);
                }

                // Auto-fetch companyContext if not provided (null or empty array)
                if ($companyContext === null || empty($companyContext)) {
                    $companyContext = $this->getCompanyContext($companyId);
                }
            } else {
                // For greetings, use minimal context (just company name)
                if ($companyContext === null || empty($companyContext)) {
                    $companyContext = $this->getCompanyContext($companyId);
                }
                $assetContext = []; // Empty context for greetings
            }
            
            // Build system message
            $systemMessage = $this->buildSystemMessage($assetContext, $companyContext, $isSimpleGreeting);
            
            // Prepare messages for OpenAI
            $openAIMessages = [];
            $openAIMessages[] = [
                'role' => 'system',
                'content' => $systemMessage
            ];

            // Add user messages (skip system messages from frontend)
            foreach ($messages as $msg) {
                if ($msg['role'] !== 'system') {
                    $openAIMessages[] = $msg;
                }
            }

            // Limit message history to prevent token overflow
            if (count($openAIMessages) > 10) {
                $openAIMessages = array_merge(
                    [$openAIMessages[0]], // Keep system message
                    array_slice($openAIMessages, -9) // Keep last 9 messages
                );
            }

            // For simple greetings, don't use tools - return direct response
            if ($isSimpleGreeting) {
                $response = $this->openAIClient->chat($openAIMessages, [], [
                    'tool_choice' => 'none', // Explicitly disable tools for greetings
                ]);
                
                return [
                    'success' => true,
                    'reply' => $response['content'] ?? 'Hello! How can I help you with your assets today?',
                    'usage' => $response['usage'] ?? [
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0
                    ]
                ];
            }

            // Get tool definitions for data queries
            $tools = $this->toolRegistry->getToolDefinitions();

            // Call OpenAI with function calling
            $response = $this->openAIClient->chat($openAIMessages, $tools, [
                'tool_choice' => 'auto',
            ]);

            // Handle tool calls if present
            if (!empty($response['tool_calls'])) {
                return $this->handleToolCalls($openAIMessages, $response, $companyId);
            }

            // Return direct response if no tool calls
            return [
                'success' => true,
                'reply' => $response['content'] ?? 'I apologize, but I could not generate a response.',
                'usage' => $response['usage'] ?? [
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ]
            ];

        } catch (Exception $e) {
            $requestId = uniqid('nlq_', true);
            Log::error('OpenAI chat failed', [
                'request_id' => $requestId,
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'messages_count' => count($messages)
            ]);
            
            return [
                'success' => false,
                'error' => 'AI processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle tool calls from OpenAI and make follow-up request.
     * Supports recursive tool calls with max depth limit.
     * 
     * @param array $messages Conversation messages
     * @param array $response OpenAI response with tool calls
     * @param string $companyId Company ID for tenant scoping
     * @param int $depth Current recursion depth (default: 0)
     * @return array Response array with success, reply, and usage
     */
    private function handleToolCalls(array $messages, array $response, string $companyId, int $depth = 0): array
    {
        // Maximum depth limit to prevent infinite loops
        $maxDepth = 3;
        if ($depth >= $maxDepth) {
            Log::warning('Tool call max depth exceeded', [
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'depth' => $depth,
                'max_depth' => $maxDepth,
            ]);
            return [
                'success' => false,
                'error' => 'Maximum tool call depth exceeded. Please simplify your query.',
                'usage' => [
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0
                ]
            ];
        }

        $toolCalls = $response['tool_calls'] ?? [];
        $toolResults = [];

        $requestId = uniqid('nlq_', true); // Correlation ID for this request

        // Log tool call round
        Log::info('Processing tool calls', [
            'request_id' => $requestId,
            'company_id' => $companyId,
            'user_id' => Auth::id(),
            'tool_calls_count' => count($toolCalls),
            'depth' => $depth,
        ]);

        // Execute each tool call
        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall['function']['name'] ?? null;
            $arguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);

            if (!$toolName) {
                continue;
            }

            try {
                $result = $this->toolRegistry->executeTool($toolName, $arguments);
                $toolResults[] = [
                    'tool_call_id' => $toolCall['id'] ?? null,
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => json_encode($result),
                ];
            } catch (Exception $e) {
                Log::error('Tool execution error', [
                    'request_id' => $requestId,
                    'tool' => $toolName,
                    'error' => $e->getMessage(),
                    'company_id' => $companyId,
                    'user_id' => Auth::id(),
                    'depth' => $depth,
                ]);

                $toolResults[] = [
                    'tool_call_id' => $toolCall['id'] ?? null,
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => json_encode([
                        'error' => true,
                        'message' => 'Failed to execute tool: ' . $e->getMessage(),
                    ]),
                ];
            }
        }

        // Add assistant message with tool calls to conversation
        $messages[] = [
            'role' => 'assistant',
            'content' => $response['content'] ?? null,
            'tool_calls' => $toolCalls,
        ];

        // Add tool results to conversation
        $messages = array_merge($messages, $toolResults);

        // Make follow-up call to OpenAI with tool results
        $finalResponse = $this->openAIClient->chat($messages, $this->toolRegistry->getToolDefinitions(), [
            'tool_choice' => 'auto',
        ]);

        // Check if there are more tool calls (recursive handling)
        if (!empty($finalResponse['tool_calls'])) {
            Log::info('Recursive tool calls detected', [
                'request_id' => $requestId,
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'new_tool_calls_count' => count($finalResponse['tool_calls']),
                'depth' => $depth + 1,
            ]);
            
            // Recursively handle more tool calls
            return $this->handleToolCalls($messages, $finalResponse, $companyId, $depth + 1);
        }

        // Check for empty content (empty string or null)
        $content = $finalResponse['content'] ?? '';
        if (empty($content)) {
            Log::warning('Empty content in final response', [
                'request_id' => $requestId,
                'company_id' => $companyId,
                'user_id' => Auth::id(),
                'depth' => $depth,
                'has_tool_calls' => !empty($finalResponse['tool_calls']),
            ]);
            $content = 'I processed your request but could not generate a response. Please try rephrasing your question.';
        }

        return [
            'success' => true,
            'reply' => $content,
            'usage' => $finalResponse['usage'] ?? [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0
            ]
        ];
    }

    /**
     * Get the latest user message from the messages array.
     */
    private function getLatestUserMessage(array $messages): string
    {
        // Get the last user message
        $userMessages = array_filter($messages, fn($msg) => ($msg['role'] ?? '') === 'user');
        if (empty($userMessages)) {
            return '';
        }
        $latestMessage = end($userMessages);
        return trim($latestMessage['content'] ?? '');
    }

    /**
     * Check if a message is a simple greeting that doesn't need database queries.
     */
    private function isSimpleGreeting(string $message): bool
    {
        $normalized = strtolower(trim($message));
        
        // Common greeting patterns
        $greetings = [
            'hi', 'hello', 'hey', 'greetings', 'good morning', 'good afternoon', 
            'good evening', 'good night', 'hi there', 'hello there', 'hey there',
            'howdy', 'sup', 'what\'s up', 'whats up', 'yo', 'hiya'
        ];
        
        // Check if message is exactly a greeting or starts with a greeting
        foreach ($greetings as $greeting) {
            if ($normalized === $greeting || substr($normalized, 0, strlen($greeting . ' ')) === $greeting . ' ') {
                return true;
            }
        }
        
        // Check if it's just a greeting with optional punctuation
        $messageWithoutPunctuation = preg_replace('/[^\w\s]/', '', $normalized);
        return in_array($messageWithoutPunctuation, $greetings);
    }

    /**
     * Build system message with minimal context and instructions.
     * With function calling, we don't need detailed context - AI can query on demand.
     */
    private function buildSystemMessage(array $assetContext, array $companyContext, bool $isGreeting = false): string
    {
        $companyName = $companyContext['name'] ?? 'your company';
        
        $message = "You are an AI assistant for an asset management platform called AssetGo. ";
        $message .= "You are an Asset & Maintenance Analyst helping users understand and analyze their asset data through natural language queries. ";
        $message .= "You have access to tools that allow you to query the database dynamically. ";
        $message .= "Always use the available tools when you need to retrieve specific data.\n\n";
        
        // Only include high-level context if provided (for initial greeting/personalization)
        if (!empty($assetContext)) {
            $message .= "COMPANY OVERVIEW ({$companyName}):\n";
            
            // Only include summary counts if available (optional)
            if (isset($assetContext['totalAssets'])) {
                $message .= "- Total Assets: {$assetContext['totalAssets']}\n";
            }
            if (isset($assetContext['openWorkOrders'])) {
                $message .= "- Open Work Orders: {$assetContext['openWorkOrders']}\n";
            }
            if (isset($assetContext['totalLocations'])) {
                $message .= "- Total Locations: {$assetContext['totalLocations']}\n";
            }
            $message .= "\n";
        }
        
        $message .= "AVAILABLE TOOLS:\n";
        $message .= "- get_assets: Query assets with filters (status, location, category, search, overdue_maintenance, order_by, order_direction, page, per_page). ALWAYS use this tool when asked about asset conditions, status, overdue maintenance, or to list assets. Available status values: Active, Maintenance, Inactive, Retired, Archived, Pending, In Transit, Damaged, Lost, Disposed. Status filter accepts status names case-insensitively (e.g., 'active', 'Active', 'in_transit', 'In Transit'). Use overdue_maintenance=true to get assets with overdue maintenance schedules. Returns asset details including status (condition name), purchase_price, location_id, and health_score. Use status='active' to filter by condition, or overdue_maintenance=true to get assets with overdue maintenance, or call without filters to get all assets with their conditions.\n";
        $message .= "- get_work_orders: Query work orders with filters (status, priority, assigned_to, overdue, asset_id, location_id, due_date_range, order_by, order_direction, page, per_page). Returns work order details including estimated_hours and actual_hours.\n";
        $message .= "- get_locations: Query locations with filters (search, parent_id, location_type_id, order_by, order_direction, page, per_page). Returns location hierarchy.\n";
        $message .= "- get_maintenance_cost_summary: Calculate maintenance costs from work orders. Filters: asset_id, location_id, start_date, end_date, hourly_rate, use_actual_hours. Returns total cost, total hours, and cost breakdown by asset.\n";
        
        $message .= "\nINSTRUCTIONS:\n";
        if ($isGreeting) {
            $message .= "- This is a simple greeting - respond warmly and ask how you can help with their assets\n";
            $message .= "- Do NOT use any tools for greetings - just respond directly\n";
            $message .= "- Be friendly and mention that you can help with asset queries, work orders, locations, and maintenance data\n";
        } else {
            $message .= "- Use tools to retrieve specific data when answering questions - DO NOT rely on pre-provided context alone\n";
            $message .= "- For simple greetings (hi, hello, hey), respond directly without using tools\n";
            $message .= "- When asked about asset conditions, status, or 'what is the condition on each asset', ALWAYS call get_assets tool (without status filter) to get all assets with their status/condition\n";
            $message .= "- When asked about 'overdue maintenance', 'assets with overdue maintenance', or 'which assets need maintenance', use get_assets with overdue_maintenance=true\n";
            $message .= "- When querying large datasets, mention if results are limited (e.g., 'showing first 100 of 4,567')\n";
            $message .= "- For questions about 'highest value assets', use get_assets and sort by purchase_price\n";
            $message .= "- For questions about 'maintenance costs', use get_work_orders and sum actual_hours or estimated_hours\n";
            $message .= "- Provide specific insights based on the data retrieved from tools\n";
            $message .= "- Use markdown formatting for lists, tables, and emphasis\n";
            $message .= "- Be helpful, accurate, and concise\n";
            $message .= "- If you don't have specific data, say so clearly\n";
            $message .= "- Never fabricate data - only use what is returned from tools\n";
            $message .= "- Ask for clarification if a query is ambiguous or missing key filters (e.g., date range)\n";
            $message .= "- Focus on actionable insights and recommendations\n";
        }
        
        return $message;
    }

    /**
     * Check if OpenAI API key is configured.
     */
    public function hasOpenAIApiKey(): bool
    {
        return $this->openAIClient->hasApiKey();
    }
}
