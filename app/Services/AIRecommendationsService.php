<?php

namespace App\Services;

use App\Models\AIRecommendation;
use App\Models\Asset;
use App\Models\WorkOrder;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AIRecommendationsService
{
    public function __construct(private OpenAIService $openAIService) {}

    /**
     * Get recommendations with filters and pagination
     */
    public function getRecommendations(array $filters = [], int $page = 1, int $pageSize = 10): array
    {
        $companyId = Auth::user()->company_id;
        
        $query = AIRecommendation::forCompany($companyId);

        // Apply filters
        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }
        
        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }
        
        if (!empty($filters['impact'])) {
            $query->byImpact($filters['impact']);
        }
        
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }
        
        if (!empty($filters['minConfidence'])) {
            $query->minConfidence($filters['minConfidence']);
        }

        // Get total count for pagination
        $total = $query->count();
        
        // Apply pagination
        $recommendations = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        // Get summary
        $summary = $this->getSummary($companyId);

        return [
            'recommendations' => $recommendations->toArray(),
            'summary' => $summary,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => ceil($total / $pageSize)
            ]
        ];
    }

    /**
     * Generate new recommendations using OpenAI
     */
    public function generateRecommendations(): array
    {
        $companyId = Auth::user()->company_id;
        
        try {
            // Get asset context
            $assetContext = $this->getAssetContext($companyId);
            
            // Build prompt for OpenAI
            $prompt = $this->buildRecommendationsPrompt($assetContext);
            
            // Call OpenAI
            $response = $this->openAIService->chat([
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ], [
                'response_format' => ['type' => 'json_object']
            ]);

            // Parse JSON response
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['recommendations']) || !is_array($data['recommendations'])) {
                throw new Exception('Invalid response format from OpenAI');
            }

            // Process and save recommendations
            $savedRecommendations = [];
            foreach ($data['recommendations'] as $recData) {
                // Compute ROI if both values exist
                $roi = null;
                if (isset($recData['estimatedSavings']) && isset($recData['implementationCost']) && $recData['implementationCost'] > 0) {
                    $net = $recData['estimatedSavings'] - $recData['implementationCost'];
                    $roi = ($net / $recData['implementationCost']) * 100;
                }

                // Compute payback period
                $paybackPeriod = null;
                if (isset($recData['estimatedSavings']) && isset($recData['implementationCost']) && $recData['estimatedSavings'] > 0) {
                    $months = max(1, round(($recData['implementationCost'] / ($recData['estimatedSavings'] / 12))));
                    $paybackPeriod = "{$months} months";
                }

                $recommendation = AIRecommendation::create([
                    'company_id' => $companyId,
                    'rec_type' => $recData['type'] ?? 'efficiency',
                    'title' => $recData['title'] ?? 'Untitled Recommendation',
                    'description' => $recData['description'] ?? '',
                    'impact' => $recData['impact'] ?? 'medium',
                    'priority' => $recData['priority'] ?? 'medium',
                    'estimated_savings' => $recData['estimatedSavings'] ?? null,
                    'implementation_cost' => $recData['implementationCost'] ?? null,
                    'roi' => $roi,
                    'payback_period' => $paybackPeriod,
                    'timeline' => $recData['timeline'] ?? '3 months',
                    'actions' => $recData['actions'] ?? [],
                    'confidence' => $recData['confidence'] ?? 75,
                    'implemented' => false
                ]);

                $savedRecommendations[] = $recommendation;
            }

            // Get updated summary
            $summary = $this->getSummary($companyId);

            return [
                'recommendations' => $savedRecommendations,
                'summary' => $summary
            ];

        } catch (Exception $e) {
            Log::error('Failed to generate AI recommendations', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Toggle implementation status
     */
    public function toggleImplementation(string $recommendationId, bool $implemented): AIRecommendation
    {
        $companyId = Auth::user()->company_id;
        
        $recommendation = AIRecommendation::forCompany($companyId)
            ->findOrFail($recommendationId);
            
        $recommendation->update(['implemented' => $implemented]);
        
        return $recommendation;
    }

    /**
     * Get summary statistics
     */
    public function getSummary(string $companyId): array
    {
        $summary = DB::table('ai_recommendations_summary')
            ->where('company_id', $companyId)
            ->first();

        if (!$summary) {
            return [
                'totalRecommendations' => 0,
                'highPriorityCount' => 0,
                'totalSavings' => 0,
                'totalCost' => 0,
                'roi' => 0,
                'lastUpdated' => null
            ];
        }

        return [
            'totalRecommendations' => $summary->total_recommendations,
            'highPriorityCount' => $summary->high_priority_count,
            'totalSavings' => $summary->total_savings,
            'totalCost' => $summary->total_cost,
            'roi' => $summary->roi,
            'lastUpdated' => $summary->last_updated
        ];
    }

    /**
     * Get asset context for recommendations
     */
    private function getAssetContext(string $companyId): array
    {
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

        // Get location count
        $locationCount = Location::where('company_id', $companyId)->count();

        return [
            'totalAssets' => $assetCounts->total_assets ?? 0,
            'activeAssets' => $assetCounts->active_assets ?? 0,
            'maintenanceAssets' => $assetCounts->maintenance_assets ?? 0,
            'totalValue' => $assetCounts->total_value ?? 0,
            'openWorkOrders' => $workOrderCounts->open_work_orders ?? 0,
            'highPriorityWorkOrders' => $workOrderCounts->high_priority_work_orders ?? 0,
            'overdueWorkOrders' => $workOrderCounts->overdue_work_orders ?? 0,
            'totalLocations' => $locationCount
        ];
    }

    /**
     * Build recommendations prompt for OpenAI
     */
    private function buildRecommendationsPrompt(array $assetContext): string
    {
        $prompt = "Analyze our asset management data and provide AI-powered recommendations to optimize operations. ";
        $prompt .= "Return ONLY a valid JSON object with a 'recommendations' array. Each recommendation must have:\n\n";
        
        $prompt .= "Required fields:\n";
        $prompt .= "- id: unique string identifier\n";
        $prompt .= "- type: one of 'cost_optimization', 'maintenance', 'efficiency', 'compliance'\n";
        $prompt .= "- title: concise recommendation title\n";
        $prompt .= "- description: detailed explanation\n";
        $prompt .= "- impact: 'low', 'medium', or 'high'\n";
        $prompt .= "- priority: 'low', 'medium', or 'high'\n";
        $prompt .= "- estimatedSavings: number in AED (or null)\n";
        $prompt .= "- implementationCost: number in AED (or null)\n";
        $prompt .= "- timeline: string like '3 months'\n";
        $prompt .= "- actions: array of action items\n";
        $prompt .= "- confidence: number 0-100\n";
        $prompt .= "- paybackPeriod: string like '6 months' (optional)\n\n";
        
        $prompt .= "Current data context:\n";
        $prompt .= "- Total Assets: {$assetContext['totalAssets']}\n";
        $prompt .= "- Active Assets: {$assetContext['activeAssets']}\n";
        $prompt .= "- Assets in Maintenance: {$assetContext['maintenanceAssets']}\n";
        $prompt .= "- Total Asset Value: AED " . number_format($assetContext['totalValue']) . "\n";
        $prompt .= "- Open Work Orders: {$assetContext['openWorkOrders']}\n";
        $prompt .= "- High Priority Work Orders: {$assetContext['highPriorityWorkOrders']}\n";
        $prompt .= "- Overdue Work Orders: {$assetContext['overdueWorkOrders']}\n";
        $prompt .= "- Total Locations: {$assetContext['totalLocations']}\n\n";
        
        $prompt .= "Focus on practical, actionable recommendations that can improve asset management efficiency, ";
        $prompt .= "reduce costs, optimize maintenance schedules, and ensure compliance. ";
        $prompt .= "Provide 5-8 diverse recommendations across different types.\n\n";
        
        $prompt .= "Return JSON only, no additional text.";
        
        return $prompt;
    }
}
