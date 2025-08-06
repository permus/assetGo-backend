<?php

namespace App\Console\Commands;

use App\Jobs\ProcessBulkAssetImport;
use App\Models\AssetImportJob;
use Illuminate\Console\Command;

class ProcessAssetImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'assets:process-import 
                            {job_id : The UUID of the import job to process}
                            {--force : Force processing even if job is already completed}';

    /**
     * The console command description.
     */
    protected $description = 'Process a queued asset import job manually';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $jobId = $this->argument('job_id');
        $force = $this->option('force');

        // Find the import job
        $importJob = AssetImportJob::where('job_id', $jobId)->first();

        if (!$importJob) {
            $this->error("Import job with ID '{$jobId}' not found.");
            return self::FAILURE;
        }

        // Check if job is already completed
        if ($importJob->is_completed && !$force) {
            $this->warn("Import job '{$jobId}' is already completed.");
            $this->info("Status: {$importJob->status}");
            $this->info("Processed: {$importJob->processed_assets}/{$importJob->total_assets}");
            $this->info("Successful: {$importJob->successful_imports}");
            $this->info("Failed: {$importJob->failed_imports}");
            
            if (!$this->confirm('Do you want to reprocess this job?')) {
                return self::SUCCESS;
            }
        }

        // Display job information
        $this->info("Processing import job: {$jobId}");
        $this->info("User: {$importJob->user->name} ({$importJob->user->email})");
        $this->info("Company: {$importJob->company->name}");
        $this->info("Total assets: {$importJob->total_assets}");
        $this->info("Current status: {$importJob->status}");

        // Confirm processing
        if (!$force && !$this->confirm('Do you want to process this import job?')) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        try {
            // Reset job status if forcing
            if ($force) {
                $importJob->update([
                    'status' => 'pending',
                    'processed_assets' => 0,
                    'successful_imports' => 0,
                    'failed_imports' => 0,
                    'errors' => null,
                    'imported_assets' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'error_message' => null,
                ]);
            }

            $this->info('Starting import job processing...');
            
            // Process the job synchronously in console
            $job = new ProcessBulkAssetImport($importJob);
            $job->handle();

            // Refresh the model to get updated data
            $importJob->refresh();

            $this->info('Import job completed successfully!');
            $this->info("Final status: {$importJob->status}");
            $this->info("Processed: {$importJob->processed_assets}/{$importJob->total_assets}");
            $this->info("Successful: {$importJob->successful_imports}");
            $this->info("Failed: {$importJob->failed_imports}");

            // Show errors if any
            if ($importJob->failed_imports > 0 && $importJob->errors) {
                $this->warn("\nErrors encountered:");
                foreach (array_slice($importJob->errors, 0, 10) as $error) {
                    $this->line("- Row {$error['index']}: {$error['error']}");
                }
                
                if (count($importJob->errors) > 10) {
                    $this->line("... and " . (count($importJob->errors) - 10) . " more errors.");
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to process import job: " . $e->getMessage());
            $this->error("File: " . $e->getFile());
            $this->error("Line: " . $e->getLine());
            
            return self::FAILURE;
        }
    }
}