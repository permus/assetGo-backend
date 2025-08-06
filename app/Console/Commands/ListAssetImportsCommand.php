<?php

namespace App\Console\Commands;

use App\Models\AssetImportJob;
use Illuminate\Console\Command;

class ListAssetImportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'assets:list-imports 
                            {--status= : Filter by status (pending, processing, completed, failed)}
                            {--user= : Filter by user ID}
                            {--company= : Filter by company ID}
                            {--limit=20 : Number of results to show}';

    /**
     * The console command description.
     */
    protected $description = 'List asset import jobs with their status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $status = $this->option('status');
        $userId = $this->option('user');
        $companyId = $this->option('company');
        $limit = (int) $this->option('limit');

        // Build query
        $query = AssetImportJob::with(['user', 'company'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $importJobs = $query->limit($limit)->get();

        if ($importJobs->isEmpty()) {
            $this->info('No import jobs found.');
            return self::SUCCESS;
        }

        // Prepare table data
        $headers = [
            'Job ID',
            'Status',
            'User',
            'Company',
            'Total',
            'Processed',
            'Success',
            'Failed',
            'Progress %',
            'Created',
            'Completed'
        ];

        $rows = [];
        foreach ($importJobs as $job) {
            $rows[] = [
                substr($job->job_id, 0, 8) . '...',
                $this->getStatusWithColor($job->status),
                $job->user->name ?? 'Unknown',
                $job->company->name ?? 'Unknown',
                $job->total_assets,
                $job->processed_assets,
                $job->successful_imports,
                $job->failed_imports,
                number_format($job->progress_percentage, 1) . '%',
                $job->created_at->format('M j, H:i'),
                $job->completed_at ? $job->completed_at->format('M j, H:i') : '-'
            ];
        }

        $this->table($headers, $rows);

        // Show summary
        $totalJobs = $importJobs->count();
        $pendingJobs = $importJobs->where('status', 'pending')->count();
        $processingJobs = $importJobs->where('status', 'processing')->count();
        $completedJobs = $importJobs->where('status', 'completed')->count();
        $failedJobs = $importJobs->where('status', 'failed')->count();

        $this->info("\nSummary:");
        $this->line("Total jobs: {$totalJobs}");
        $this->line("Pending: {$pendingJobs}");
        $this->line("Processing: {$processingJobs}");
        $this->line("Completed: {$completedJobs}");
        $this->line("Failed: {$failedJobs}");

        return self::SUCCESS;
    }

    /**
     * Get status with color formatting
     */
    private function getStatusWithColor(string $status): string
    {
        return match ($status) {
            'pending' => "<fg=yellow>{$status}</>",
            'processing' => "<fg=blue>{$status}</>",
            'completed' => "<fg=green>{$status}</>",
            'failed' => "<fg=red>{$status}</>",
            default => $status,
        };
    }
}