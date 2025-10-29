<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\PredictiveMaintenance;
use App\Models\PredictiveMaintenanceJob;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePredictiveMaintenancePredictions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $companyId,
        private array $assetIds = [],
        private bool $forceRefresh = false,
        private string $jobId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Update job status to processing
            $jobRecord = PredictiveMaintenanceJob::where('job_id', $this->jobId)->first();
            if ($jobRecord) {
                $jobRecord->update(['status' => 'processing']);
            }

            // If no specific assets provided, get all active assets for the company
            if (empty($this->assetIds)) {
                $assets = Asset::where('company_id', $this->companyId)
                    ->where('is_active', true)
                    ->with(['maintenanceSchedules', 'activities'])
                    ->get();
            } else {
                $assets = Asset::where('company_id', $this->companyId)
                    ->whereIn('id', $this->assetIds)
                    ->where('is_active', true)
                    ->with(['maintenanceSchedules', 'activities'])
                    ->get();
            }

            if ($assets->isEmpty()) {
                throw new \Exception('No assets found for analysis');
            }

            if ($jobRecord) {
                $jobRecord->update(['total_assets' => $assets->count()]);
            }

            // Prepare asset data for AI analysis
            $assetData = $this->prepareAssetDataForAI($assets);

            // Call OpenAI to generate predictions
            $openAIService = app(OpenAIService::class);
            $predictions = $this->callOpenAIForPredictions($assetData, $openAIService);

            // Clear existing predictions if force refresh
            if ($this->forceRefresh) {
                PredictiveMaintenance::where('company_id', $this->companyId)->delete();
            }

            // Store predictions in database
            $storedPredictions = $this->storePredictions($predictions, $this->companyId);

            // Update job with progress
            if ($jobRecord) {
                $jobRecord->update([
                    'status' => 'completed',
                    'predictions_generated' => count($storedPredictions),
                    'progress' => 100,
                    'completed_at' => now()
                ]);
            }

            Log::info('Predictive maintenance predictions generated successfully', [
                'job_id' => $this->jobId,
                'company_id' => $this->companyId,
                'predictions_count' => count($storedPredictions)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate predictive maintenance predictions', [
                'job_id' => $this->jobId,
                'company_id' => $this->companyId,
                'error' => $e->getMessage()
            ]);

            // Update job status to failed
            $jobRecord = PredictiveMaintenanceJob::where('job_id', $this->jobId)->first();
            if ($jobRecord) {
                $jobRecord->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now()
                ]);
            }

            throw $e;
        }
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
    private function callOpenAIForPredictions(array $assetData, OpenAIService $openAI): array
    {
        $prompt = $this->buildPredictionPrompt($assetData);
        
        try {
            $response = $openAI->generateText($prompt);
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
}

