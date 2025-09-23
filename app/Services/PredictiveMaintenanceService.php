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
     */
    public function generatePredictions(array $assetIds = [], bool $forceRefresh = false): array
    {
        $companyId = Auth::user()->company_id;
        
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
            'predictions' => $predictions,
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
    private function prepareAssetDataForAI($assets): array
    {
        return $assets->map(function ($asset) {
            // Get maintenance history
            $maintenanceHistory = $asset->activities()
                ->where('activity_type', 'maintenance')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['activity_type', 'description', 'created_at']);

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
    private function callOpenAIForPredictions(array $assetData): array
    {
        $prompt = $this->buildPredictionPrompt($assetData);
        
        try {
            $response = $this->openAI->generateText($prompt);
            $predictions = json_decode($response, true);
            
            if (!is_array($predictions)) {
                throw new \Exception('Invalid response format from AI');
            }
            
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
     * Build the prompt for AI prediction generation.
     */
    private function buildPredictionPrompt(array $assetData): string
    {
        return "You are an expert predictive maintenance AI. Analyze the following assets and predict potential failures.

Asset Data:
" . json_encode($assetData, JSON_PRETTY_PRINT) . "

Return ONLY a JSON array of prediction objects with this exact structure:
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

Be realistic with cost estimates and failure dates. Focus on actionable insights.";
    }

    /**
     * Store predictions in the database.
     */
    private function storePredictions(array $predictions, string $companyId): array
    {
        $storedPredictions = [];
        
        foreach ($predictions as $prediction) {
            // Validate required fields
            if (!isset($prediction['assetId']) || !isset($prediction['assetName'])) {
                continue;
            }

            $storedPrediction = PredictiveMaintenance::create([
                'asset_id' => $prediction['assetId'],
                'risk_level' => $prediction['riskLevel'] ?? 'medium',
                'predicted_failure_date' => $prediction['predictedFailureDate'] ?? null,
                'confidence' => $prediction['confidence'] ?? 50,
                'recommended_action' => $prediction['recommendedAction'] ?? 'Schedule maintenance inspection',
                'estimated_cost' => $prediction['estimatedCost'] ?? 0,
                'preventive_cost' => $prediction['preventiveCost'] ?? 0,
                'savings' => $prediction['savings'] ?? 0,
                'factors' => $prediction['factors'] ?? [],
                'timeline' => $prediction['timeline'] ?? [],
                'company_id' => $companyId,
            ]);

            $storedPrediction->load('asset');
            $storedPredictions[] = $storedPrediction;
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
                COUNT(*) as total_predictions,
                COUNT(CASE WHEN risk_level = "high" THEN 1 END) as high_risk_count,
                COALESCE(SUM(savings), 0) as total_savings,
                COALESCE(AVG(confidence), 0) as avg_confidence,
                MAX(created_at) as last_updated
            ')
            ->first();

        return [
            'totalAssets' => $summary->total_predictions,
            'highRiskCount' => $summary->high_risk_count,
            'totalSavings' => (float) $summary->total_savings,
            'averageConfidence' => round((float) $summary->avg_confidence, 2),
            'lastUpdated' => $summary->last_updated,
        ];
    }
}
