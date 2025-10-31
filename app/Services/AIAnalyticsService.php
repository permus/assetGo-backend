<?php

namespace App\Services;

use App\Models\AIAnalyticsRun;
use App\Models\AIAnalyticsSchedule;
use App\Models\Asset;
use App\Models\WorkOrder;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AIAnalyticsService
{
    public function __construct(private OpenAIService $openAIService) {}

    /**
     * Get latest analytics and history
     */
    public function getAnalytics(): array
    {
        $companyId = Auth::user()->company_id;
        
        // Get latest analytics run
        $latest = AIAnalyticsRun::forCompany($companyId)
            ->latest()
            ->first();

        // Get history (last 12 runs)
        $history = AIAnalyticsRun::forCompany($companyId)
            ->select('created_at', 'health_score')
            ->latest()
            ->limit(12)
            ->get()
            ->map(function($run) {
                return [
                    'createdAt' => $run->created_at->toISOString(),
                    'healthScore' => $run->health_score
                ];
            })
            ->toArray();

        // Get asset context for avgAssetAge
        $assetContext = $this->getAssetContext($companyId);
        
        $result = [
            'latest' => $latest ? $this->formatAnalyticsSnapshot($latest) : null,
            'history' => $history
        ];
        
        // Add avgAssetAge to latest if available
        if ($result['latest']) {
            $result['latest']['avgAssetAge'] = $assetContext['avgAssetAge'] ?? 0;
        }

        return $result;
    }

    /**
     * Generate new analytics using OpenAI
     */
    public function generateAnalytics(): array
    {
        $companyId = Auth::user()->company_id;
        
        try {
            // Get asset context
            $assetContext = $this->getAssetContext($companyId);
            
            // Build prompt for OpenAI
            $prompt = $this->buildAnalyticsPrompt($assetContext);
            
            // Call OpenAI
            $response = $this->openAIService->chat([
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ], [
                'response_format' => ['type' => 'json_object']
            ]);

            // Extract content from response array
            $content = is_array($response) ? $response['content'] : $response;

            // Parse JSON response
            $data = json_decode($content, true);
            
            if (!$data || !isset($data['healthScore'])) {
                throw new Exception('Invalid response format from OpenAI');
            }

            // Validate required fields
            $this->validateAnalyticsData($data);

            // Create analytics run record
            $analyticsRun = AIAnalyticsRun::create([
                'company_id' => $companyId,
                'payload' => $data,
                'health_score' => $data['healthScore']
            ]);

            // Get updated analytics
            $result = $this->getAnalytics();
            $formattedSnapshot = $this->formatAnalyticsSnapshot($analyticsRun);
            // Add avgAssetAge from context
            $formattedSnapshot['avgAssetAge'] = $assetContext['avgAssetAge'] ?? 0;
            $result['latest'] = $formattedSnapshot;

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to generate AI analytics', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get schedule settings
     */
    public function getScheduleSettings(): array
    {
        $companyId = Auth::user()->company_id;
        
        $schedule = AIAnalyticsSchedule::where('company_id', $companyId)->first();
        
        if (!$schedule) {
            return [
                'enabled' => false,
                'frequency' => 'weekly',
                'hourUTC' => 3
            ];
        }

        return [
            'enabled' => $schedule->enabled,
            'frequency' => $schedule->frequency,
            'hourUTC' => $schedule->hour_utc
        ];
    }

    /**
     * Update schedule settings
     */
    public function updateScheduleSettings(array $settings): array
    {
        $companyId = Auth::user()->company_id;
        
        $schedule = AIAnalyticsSchedule::updateOrCreate(
            ['company_id' => $companyId],
            [
                'enabled' => $settings['enabled'] ?? false,
                'frequency' => $settings['frequency'] ?? 'weekly',
                'hour_utc' => $settings['hourUTC'] ?? 3
            ]
        );

        return [
            'enabled' => $schedule->enabled,
            'frequency' => $schedule->frequency,
            'hourUTC' => $schedule->hour_utc
        ];
    }

    /**
     * Export analytics data to CSV
     */
    public function exportAnalytics(): string
    {
        $companyId = Auth::user()->company_id;
        
        $latest = AIAnalyticsRun::forCompany($companyId)
            ->latest()
            ->first();

        if (!$latest) {
            return '';
        }

        $data = $latest->payload;
        $csvData = [];

        // Add risk assets
        foreach ($data['riskAssets'] ?? [] as $asset) {
            $csvData[] = [
                'Type' => 'Risk Asset',
                'Title' => $asset['name'] ?? '',
                'Description' => $asset['reason'] ?? '',
                'Risk Level' => $asset['riskLevel'] ?? '',
                'Confidence' => $asset['confidence'] ?? 0,
                'Action' => $asset['recommendedAction'] ?? '',
                'Estimated Cost' => $asset['estimatedCost'] ?? '',
                'Preventive Cost' => $asset['preventiveCost'] ?? ''
            ];
        }

        // Add performance insights
        foreach ($data['performanceInsights'] ?? [] as $insight) {
            $csvData[] = [
                'Type' => 'Performance Insight',
                'Title' => $insight['title'] ?? '',
                'Description' => $insight['description'] ?? '',
                'Impact' => $insight['impact'] ?? '',
                'Confidence' => $insight['confidence'] ?? 0,
                'Action' => $insight['action'] ?? '',
                'Category' => $insight['category'] ?? '',
                'Estimated Cost' => '',
                'Preventive Cost' => ''
            ];
        }

        // Add cost optimizations
        foreach ($data['costOptimizations'] ?? [] as $optimization) {
            $csvData[] = [
                'Type' => 'Cost Optimization',
                'Title' => $optimization['title'] ?? '',
                'Description' => $optimization['description'] ?? '',
                'Impact' => '',
                'Confidence' => $optimization['confidence'] ?? 0,
                'Action' => '',
                'Category' => $optimization['category'] ?? '',
                'Estimated Cost' => $optimization['estimatedSavings'] ?? '',
                'Preventive Cost' => $optimization['paybackPeriod'] ?? ''
            ];
        }

        return $this->arrayToCsv($csvData);
    }

    /**
     * Get asset context for analytics
     */
    private function getAssetContext(string $companyId): array
    {
        return Cache::remember("ai-analytics-context-{$companyId}", 300, function () use ($companyId) {
            // Get asset counts and health indicators
            $assetCounts = Asset::where('company_id', $companyId)
            ->selectRaw('
                COUNT(*) as total_assets,
                COUNT(CASE WHEN status = "active" THEN 1 END) as active_assets,
                COUNT(CASE WHEN status = "maintenance" THEN 1 END) as maintenance_assets,
                COUNT(CASE WHEN status = "inactive" THEN 1 END) as inactive_assets,
                AVG(CASE WHEN purchase_date IS NOT NULL THEN YEAR(CURDATE()) - YEAR(purchase_date) END) as avg_age,
                SUM(COALESCE(purchase_price, 0)) as total_value
            ')
            ->first();

        // Get work order counts
        $workOrderCounts = WorkOrder::where('work_orders.company_id', $companyId)
            ->leftJoin('work_order_status', 'work_orders.status_id', '=', 'work_order_status.id')
            ->leftJoin('work_order_priority', 'work_orders.priority_id', '=', 'work_order_priority.id')
            ->selectRaw('
                COUNT(*) as total_work_orders,
                COUNT(CASE WHEN work_order_status.slug = "open" THEN 1 END) as open_work_orders,
                COUNT(CASE WHEN work_order_priority.slug = "high" OR work_order_priority.slug = "critical" THEN 1 END) as high_priority_work_orders,
                COUNT(CASE WHEN work_orders.due_date < NOW() AND work_order_status.slug NOT IN ("completed", "cancelled") THEN 1 END) as overdue_work_orders
            ')
            ->first();

            // Get location count
            $locationCount = Location::where('company_id', $companyId)->count();

            return [
                'totalAssets' => $assetCounts->total_assets ?? 0,
                'activeAssets' => $assetCounts->active_assets ?? 0,
                'maintenanceAssets' => $assetCounts->maintenance_assets ?? 0,
                'inactiveAssets' => $assetCounts->inactive_assets ?? 0,
                'avgAssetAge' => round($assetCounts->avg_age ?? 0, 1),
                'totalValue' => $assetCounts->total_value ?? 0,
                'totalWorkOrders' => $workOrderCounts->total_work_orders ?? 0,
                'openWorkOrders' => $workOrderCounts->open_work_orders ?? 0,
                'highPriorityWorkOrders' => $workOrderCounts->high_priority_work_orders ?? 0,
                'overdueWorkOrders' => $workOrderCounts->overdue_work_orders ?? 0,
                'totalLocations' => $locationCount
            ];
        });
    }

    /**
     * Build analytics prompt for OpenAI
     */
    private function buildAnalyticsPrompt(array $assetContext): string
    {
        $prompt = "Analyze our asset portfolio and return ONLY valid JSON with the following structure:\n\n";
        
        $prompt .= "Required fields:\n";
        $prompt .= "- healthScore: number 0-100 (overall asset health)\n";
        $prompt .= "- riskAssets: array of objects with id, name, riskLevel (high/medium/low), reason, confidence (0-100), recommendedAction, estimatedCost, preventiveCost\n";
        $prompt .= "- performanceInsights: array of objects with title, description, impact (low/medium/high), action, confidence (0-100), category (utilization/efficiency/maintenance/cost)\n";
        $prompt .= "- costOptimizations: array of objects with title, description, estimatedSavings, paybackPeriod, confidence (0-100), category (energy/maintenance/vendor/lifecycle)\n";
        $prompt .= "- trends: array of objects with date (YYYY-MM-DD), healthScore, maintenanceCost (optional)\n\n";
        
        $prompt .= "Current asset data:\n";
        $prompt .= "- Total Assets: {$assetContext['totalAssets']}\n";
        $prompt .= "- Active Assets: {$assetContext['activeAssets']}\n";
        $prompt .= "- Assets in Maintenance: {$assetContext['maintenanceAssets']}\n";
        $prompt .= "- Inactive Assets: {$assetContext['inactiveAssets']}\n";
        $prompt .= "- Average Asset Age: {$assetContext['avgAssetAge']} years\n";
        $prompt .= "- Total Asset Value: AED " . number_format($assetContext['totalValue']) . "\n";
        $prompt .= "- Total Work Orders: {$assetContext['totalWorkOrders']}\n";
        $prompt .= "- Open Work Orders: {$assetContext['openWorkOrders']}\n";
        $prompt .= "- High Priority Work Orders: {$assetContext['highPriorityWorkOrders']}\n";
        $prompt .= "- Overdue Work Orders: {$assetContext['overdueWorkOrders']}\n";
        $prompt .= "- Total Locations: {$assetContext['totalLocations']}\n\n";
        
        $prompt .= "Provide 3-5 risk assets, 4-6 performance insights, and 3-5 cost optimizations. ";
        $prompt .= "Use AED for all monetary values. Return JSON only, no additional text.";
        
        return $prompt;
    }

    /**
     * Validate analytics data structure
     */
    private function validateAnalyticsData(array $data): void
    {
        $requiredFields = ['healthScore', 'riskAssets', 'performanceInsights', 'costOptimizations'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if (!is_numeric($data['healthScore']) || $data['healthScore'] < 0 || $data['healthScore'] > 100) {
            throw new Exception('Invalid health score');
        }

        if (!is_array($data['riskAssets']) || !is_array($data['performanceInsights']) || !is_array($data['costOptimizations'])) {
            throw new Exception('Invalid data structure');
        }
    }

    /**
     * Format analytics snapshot for API response
     */
    private function formatAnalyticsSnapshot(AIAnalyticsRun $run): array
    {
        return [
            'id' => (string) $run->id,
            'companyId' => (string) $run->company_id,
            'createdAt' => $run->created_at->toISOString(),
            'healthScore' => $run->health_score,
            'riskAssets' => $run->payload['riskAssets'] ?? [],
            'performanceInsights' => $run->payload['performanceInsights'] ?? [],
            'costOptimizations' => $run->payload['costOptimizations'] ?? [],
            'trends' => $run->payload['trends'] ?? []
        ];
    }

    /**
     * Convert array to CSV
     */
    private function arrayToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }
        
        $keys = array_keys($data[0]);
        $header = implode(',', $keys);
        
        $rows = array_map(function($row) use ($keys) {
            return implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value ?? '') . '"';
            }, array_values(array_intersect_key($row, array_flip($keys)))));
        }, $data);
        
        return $header . "\n" . implode("\n", $rows);
    }
}
