<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\PredictiveMaintenance;
use App\Models\PredictiveMaintenanceJob;
use App\Services\PredictiveMaintenanceService;
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

            // Use PredictiveMaintenanceService instead of duplicating code
            $service = app(PredictiveMaintenanceService::class);

            // Step 1: Prepare asset data for AI analysis (25% progress)
            $assetData = $service->prepareAssetDataForAI($assets);
            if ($jobRecord) {
                $jobRecord->update(['progress' => 25]);
            }

            // Step 2: Call OpenAI to generate predictions (50% progress)
            $predictions = $service->callOpenAIForPredictions($assetData);
            if ($jobRecord) {
                $jobRecord->update(['progress' => 50]);
            }

            // Clear existing predictions if force refresh
            if ($this->forceRefresh) {
                PredictiveMaintenance::where('company_id', $this->companyId)->delete();
            }

            // Step 3: Store predictions in database (75% progress)
            $storedPredictions = $service->storePredictions($predictions, $this->companyId);
            if ($jobRecord) {
                $jobRecord->update(['progress' => 75]);
            }

            // Step 4: Complete (100% progress)
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
}

