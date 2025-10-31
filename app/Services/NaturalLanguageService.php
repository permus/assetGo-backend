<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\WorkOrder;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class NaturalLanguageService
{
    public function __construct(private OpenAIService $openAIService) {}

    /**
     * Get asset context for natural language queries.
     */
    public function getAssetContext(string $companyId): array
    {
        return Cache::remember("nlq-context-{$companyId}", 300, function () use ($companyId) {
            // Get asset counts
        $assetCounts = Asset::where('company_id', $companyId)
            ->selectRaw('
                COUNT(*) as total_assets,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active_assets,
                COUNT(CASE WHEN status = "maintenance" THEN 1 END) as maintenance_assets,
                SUM(COALESCE(purchase_price, 0)) as total_value
            ')
            ->first();

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

        // Get recent assets (max 5)
        $recentAssets = Asset::where('company_id', $companyId)
            ->select('id', 'name', 'status')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function($asset) {
                return [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'status' => $asset->status
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
     * Process natural language chat query.
     */
    public function processChatQuery(array $messages, array $assetContext, array $companyContext, string $companyId): array
    {
        try {
            // Prepare the system message with context
            $systemMessage = $this->buildSystemMessage($assetContext, $companyContext);
            
            // Add system message to the beginning of messages
            array_unshift($messages, [
                'role' => 'system',
                'content' => $systemMessage
            ]);

            // Limit message history to prevent token overflow
            if (count($messages) > 10) {
                $messages = array_slice($messages, -10);
            }

            // Call OpenAI
            $response = $this->openAIService->chat($messages, [
                'response_format' => ['type' => 'text']
            ]);

            return [
                'success' => true,
                'reply' => is_array($response) ? $response['content'] : $response,
                'usage' => is_array($response) && isset($response['usage']) 
                    ? $response['usage'] 
                    : [
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0
                    ]
            ];

        } catch (Exception $e) {
            Log::error('OpenAI chat failed', [
                'company_id' => $companyId,
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
     * Build system message with asset context.
     */
    private function buildSystemMessage(array $assetContext, array $companyContext): string
    {
        $companyName = $companyContext['name'] ?? 'your company';
        
        $message = "You are an AI assistant for an asset management platform called AssetGo. ";
        $message .= "You help users understand and analyze their asset data through natural language queries. ";
        $message .= "You have access to the following information about {$companyName}:\n\n";
        
        $message .= "ASSET OVERVIEW:\n";
        $message .= "- Total Assets: {$assetContext['totalAssets']}\n";
        $message .= "- Active Assets: {$assetContext['activeAssets']}\n";
        $message .= "- Assets in Maintenance: {$assetContext['maintenanceAssets']}\n";
        
        if (isset($assetContext['totalValue']) && $assetContext['totalValue'] > 0) {
            $message .= "- Total Asset Value: $" . number_format($assetContext['totalValue']) . "\n";
        }
        
        $message .= "\nWORK ORDERS:\n";
        $message .= "- Open Work Orders: {$assetContext['openWorkOrders']}\n";
        $message .= "- High Priority: {$assetContext['highPriorityWorkOrders']}\n";
        $message .= "- Overdue: {$assetContext['overdueWorkOrders']}\n";
        
        $message .= "\nMAINTENANCE:\n";
        $message .= "- Overdue Maintenance: {$assetContext['overdueMaintenance']}\n";
        
        $message .= "\nLOCATIONS:\n";
        $message .= "- Total Locations: {$assetContext['totalLocations']}\n";
        
        if (!empty($assetContext['recentAssets'])) {
            $message .= "\nRECENT ASSETS:\n";
            foreach ($assetContext['recentAssets'] as $asset) {
                $message .= "- {$asset['name']} ({$asset['status']})\n";
            }
        }
        
        if (!empty($assetContext['recentWorkOrders'])) {
            $message .= "\nRECENT WORK ORDERS:\n";
            foreach ($assetContext['recentWorkOrders'] as $wo) {
                $message .= "- {$wo['title']} ({$wo['status']})\n";
            }
        }
        
        $message .= "\nINSTRUCTIONS:\n";
        $message .= "- Answer questions about assets, maintenance, work orders, and operations\n";
        $message .= "- Provide specific insights based on the data provided\n";
        $message .= "- Use markdown formatting for lists, tables, and emphasis\n";
        $message .= "- Be helpful, accurate, and concise\n";
        $message .= "- If you don't have specific data, say so clearly\n";
        $message .= "- Focus on actionable insights and recommendations\n";
        
        return $message;
    }

    /**
     * Check if OpenAI API key is configured.
     */
    public function hasOpenAIApiKey(): bool
    {
        return !empty(config('openai.api_key'));
    }
}
