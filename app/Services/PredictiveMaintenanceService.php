<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PredictiveMaintenance;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PredictiveMaintenanceService
{
    public function __construct(private OpenAIService $openAI) {}

    /**
     * Generate predictions for assets using AI.
     * 
     * @param string|null $companyId Company ID (optional, will use Auth::user() if not provided)
     */
    public function generatePredictions(array $assetIds = [], bool $forceRefresh = false, ?string $companyId = null): array
    {
        if ($companyId === null) {
            $companyId = Auth::user()->company_id;
        }
        
        // If no specific assets provided, get all active assets for the company
        if (empty($assetIds)) {
            $assets = Asset::where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['maintenanceSchedules', 'activities'])
                ->get();
        } else {
            $assets = Asset::where('company_id', $companyId)
                ->whereIn('id', $assetIds)
                ->where('is_active', true)
                ->with(['maintenanceSchedules', 'activities'])
                ->get();
        }

        if ($assets->isEmpty()) {
            throw new \Exception('No assets found for analysis');
        }

        // Prepare asset data for AI analysis
        $assetData = $this->prepareAssetDataForAI($assets);

        // Call OpenAI to generate predictions
        $predictions = $this->callOpenAIForPredictions($assetData);

        // Clear existing predictions if force refresh
        if ($forceRefresh) {
            PredictiveMaintenance::where('company_id', $companyId)->delete();
        }

        // Store predictions in database
        $storedPredictions = $this->storePredictions($predictions, $companyId);

        // Calculate summary
        $summary = $this->calculateSummary($companyId);

        return [
            'predictions' => $storedPredictions,
            'summary' => $summary,
            'generatedAt' => now()->toISOString(),
        ];
    }

    /**
     * Get predictions with optional filtering.
     */
    public function getPredictions(array $filters = []): array
    {
        $companyId = Auth::user()->company_id;
        
        $query = PredictiveMaintenance::with('asset')
            ->where('company_id', $companyId);

        // Apply filters
        if (isset($filters['risk_level'])) {
            $query->where('risk_level', $filters['risk_level']);
        }

        if (isset($filters['min_confidence'])) {
            $query->where('confidence', '>=', $filters['min_confidence']);
        }

        if (isset($filters['date_from'])) {
            $query->where('predicted_failure_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('predicted_failure_date', '<=', $filters['date_to']);
        }

        $predictions = $query->orderBy('risk_level', 'desc')
            ->orderBy('confidence', 'desc')
            ->get();

        $summary = $this->calculateSummary($companyId);

        return [
            'predictions' => $predictions, // Will be transformed by Resource in Controller
            'summary' => $summary,
        ];
    }

    /**
     * Export predictions to CSV.
     */
    public function exportToCsv(array $filters = []): string
    {
        $data = $this->getPredictions($filters);
        $predictions = $data['predictions'];

        $csv = "Asset Name,Asset Type,Risk Level,Confidence,Predicted Failure Date,Recommended Action,Estimated Cost,Preventive Cost,Savings,ROI,Factors\n";

        foreach ($predictions as $prediction) {
            $assetName = $prediction->asset ? $prediction->asset->name : 'Unknown Asset';
            $assetType = $prediction->asset ? $prediction->asset->asset_type : 'Unknown';
            $factors = is_array($prediction->factors) ? implode('; ', $prediction->factors) : '';
            
            $csv .= sprintf(
                '"%s","%s","%s",%s,"%s","%s",%s,%s,%s,%s,"%s"' . "\n",
                $assetName,
                $assetType,
                ucfirst($prediction->risk_level),
                $prediction->confidence,
                $prediction->predicted_failure_date?->format('Y-m-d') ?? '',
                $prediction->recommended_action,
                $prediction->estimated_cost,
                $prediction->preventive_cost,
                $prediction->savings,
                $prediction->roi,
                $factors
            );
        }

        return $csv;
    }

    /**
     * Prepare asset data for AI analysis.
     */
    public function prepareAssetDataForAI($assets): array
    {
        return $assets->map(function ($asset) {
            // Get maintenance history
            $maintenanceHistory = $asset->activities()
                ->where('action', 'like', '%maintenance%')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['action', 'comment', 'created_at']);

            // Calculate asset age
            $age = $asset->purchase_date ? now()->diffInYears($asset->purchase_date) : 0;

            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'type' => $asset->asset_type,
                'manufacturer' => $asset->manufacturer,
                'model' => $asset->model,
                'age' => $age,
                'condition' => $asset->condition ?? 'Unknown',
                'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
                'last_maintenance' => $maintenanceHistory->first()?->created_at?->format('Y-m-d'),
                'maintenance_count' => $maintenanceHistory->count(),
                'purchase_price' => $asset->purchase_price ?? 0,
                'current_value' => $asset->current_value ?? $asset->purchase_price ?? 0,
            ];
        })->toArray();
    }

    /**
     * Call OpenAI to generate predictions.
     */
    public function callOpenAIForPredictions(array $assetData): array
    {
        $prompt = $this->buildPredictionPrompt($assetData);
        
        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ], [
                'response_format' => ['type' => 'json_object'] // Helps ensure JSON response
            ]);
            
            // Extract content from response array
            $content = is_array($response) ? $response['content'] : $response;
            
            // Extract JSON from response (handle markdown code blocks)
            $predictions = $this->extractJsonFromResponse($content);
            
            // Validate predictions structure
            $this->validateAIResponse($predictions);
            
            return $predictions;
        } catch (\Exception $e) {
            Log::error('OpenAI prediction generation failed', [
                'error' => $e->getMessage(),
                'asset_count' => count($assetData)
            ]);
            throw new \Exception('Failed to generate predictions: ' . $e->getMessage());
        }
    }

    /**
     * Extract JSON from AI response, handling markdown code blocks.
     */
    private function extractJsonFromResponse(string $response): array
    {
        // Try to decode as-is first
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try to extract JSON from markdown code blocks
        // Pattern: ```json [...] ``` or ``` [...] ```
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
            $jsonContent = trim($matches[1]);
            $decoded = json_decode($jsonContent, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Try to find JSON array in the response
        if (preg_match('/\[\s*\{[\s\S]*\}\s*\]/', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \Exception('Invalid response format from AI: Could not extract valid JSON');
    }

    /**
     * Validate AI response structure and fields.
     */
    private function validateAIResponse(array $predictions): void
    {
        if (empty($predictions)) {
            throw new \Exception('AI returned empty predictions array');
        }

        $requiredFields = ['assetId', 'assetName', 'riskLevel', 'confidence'];
        $validRiskLevels = ['high', 'medium', 'low'];

        foreach ($predictions as $index => $prediction) {
            if (!is_array($prediction)) {
                throw new \Exception("Prediction at index {$index} is not an array");
            }

            // Validate required fields
            foreach ($requiredFields as $field) {
                if (!isset($prediction[$field])) {
                    throw new \Exception("Missing required field '{$field}' in prediction at index {$index}");
                }
            }

            // Validate risk level
            if (!in_array(strtolower($prediction['riskLevel']), $validRiskLevels)) {
                throw new \Exception("Invalid risk level '{$prediction['riskLevel']}' in prediction at index {$index}. Must be: " . implode(', ', $validRiskLevels));
            }

            // Validate confidence (0-100)
            $confidence = $prediction['confidence'];
            if (!is_numeric($confidence) || $confidence < 0 || $confidence > 100) {
                throw new \Exception("Invalid confidence value '{$confidence}' in prediction at index {$index}. Must be between 0 and 100");
            }

            // Validate predicted failure date format (if provided)
            if (isset($prediction['predictedFailureDate']) && !empty($prediction['predictedFailureDate'])) {
                $date = \DateTime::createFromFormat('Y-m-d', $prediction['predictedFailureDate']);
                if (!$date || $date->format('Y-m-d') !== $prediction['predictedFailureDate']) {
                    throw new \Exception("Invalid date format '{$prediction['predictedFailureDate']}' in prediction at index {$index}. Expected YYYY-MM-DD");
                }
            }

            // Validate numeric fields
            $numericFields = ['estimatedCost', 'preventiveCost', 'savings'];
            foreach ($numericFields as $field) {
                if (isset($prediction[$field]) && !is_numeric($prediction[$field])) {
                    throw new \Exception("Invalid numeric value for '{$field}' in prediction at index {$index}");
                }
            }
        }
    }

    /**
     * Build the prompt for AI prediction generation.
     */
    public function buildPredictionPrompt(array $assetData): string
    {
        return "You are an expert predictive maintenance AI. Analyze the following assets and predict potential failures.

Asset Data:
" . json_encode($assetData, JSON_PRETTY_PRINT) . "

IMPORTANT: Return ONLY valid JSON, no markdown, no explanations, no code blocks. Return a JSON array directly.

Return a JSON array of prediction objects with this exact structure:
[
  {
    \"assetId\": \"string\",
    \"assetName\": \"string\",
    \"riskLevel\": \"high|medium|low\",
    \"predictedFailureDate\": \"YYYY-MM-DD\",
    \"confidence\": 0-100,
    \"recommendedAction\": \"string\",
    \"estimatedCost\": 0,
    \"preventiveCost\": 0,
    \"savings\": 0,
    \"factors\": [\"string1\", \"string2\"],
    \"timeline\": {
      \"immediate\": [\"action1\"],
      \"shortTerm\": [\"action2\"],
      \"longTerm\": [\"action3\"]
    }
  }
]

Consider factors like:
- Asset age and usage patterns
- Maintenance history and frequency
- Asset type and criticality
- Environmental conditions
- Manufacturer recommendations

Be realistic with cost estimates and failure dates. Focus on actionable insights.

Remember: Return ONLY the JSON array, nothing else.";
    }

    /**
     * Store predictions in the database.
     */
    public function storePredictions(array $predictions, string $companyId): array
    {
        $storedPredictions = [];
        
        foreach ($predictions as $index => $prediction) {
            // Validate required fields (should already be validated, but double-check)
            if (!isset($prediction['assetId']) || !isset($prediction['assetName'])) {
                Log::warning('Skipping prediction with missing required fields', [
                    'index' => $index,
                    'prediction' => $prediction
                ]);
                continue;
            }

            try {
                $storedPrediction = PredictiveMaintenance::create([
                    'asset_id' => $prediction['assetId'],
                    'risk_level' => strtolower($prediction['riskLevel'] ?? 'medium'),
                    'predicted_failure_date' => !empty($prediction['predictedFailureDate']) 
                        ? $prediction['predictedFailureDate'] 
                        : null,
                    'confidence' => max(0, min(100, (float) ($prediction['confidence'] ?? 50))),
                    'recommended_action' => $prediction['recommendedAction'] ?? 'Schedule maintenance inspection',
                    'estimated_cost' => max(0, (float) ($prediction['estimatedCost'] ?? 0)),
                    'preventive_cost' => max(0, (float) ($prediction['preventiveCost'] ?? 0)),
                    'savings' => (float) ($prediction['savings'] ?? 0),
                    'factors' => is_array($prediction['factors'] ?? null) ? $prediction['factors'] : [],
                    'timeline' => is_array($prediction['timeline'] ?? null) ? $prediction['timeline'] : [],
                    'company_id' => $companyId,
                ]);

                $storedPrediction->load('asset');
                $storedPredictions[] = $storedPrediction;
            } catch (\Exception $e) {
                Log::error('Failed to store prediction', [
                    'index' => $index,
                    'asset_id' => $prediction['assetId'] ?? null,
                    'error' => $e->getMessage()
                ]);
                // Continue processing other predictions
                continue;
            }
        }

        return $storedPredictions;
    }

    /**
     * Calculate summary statistics.
     */
    public function calculateSummary(string $companyId): array
    {
        $summary = DB::table('predictive_maintenance')
            ->where('company_id', $companyId)
            ->selectRaw('
                COUNT(DISTINCT asset_id) as total_assets,
                COUNT(*) as total_predictions,
                COUNT(CASE WHEN risk_level = "high" THEN 1 END) as high_risk_count,
                COALESCE(SUM(savings), 0) as total_savings,
                COALESCE(AVG(confidence), 0) as avg_confidence,
                MAX(created_at) as last_updated
            ')
            ->first();

        return [
            'totalAssets' => $summary->total_assets ?? 0,
            'highRiskCount' => $summary->high_risk_count ?? 0,
            'totalSavings' => (float) ($summary->total_savings ?? 0),
            'averageConfidence' => round((float) ($summary->avg_confidence ?? 0), 2),
            'lastUpdated' => $summary->last_updated,
        ];
    }
}
