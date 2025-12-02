<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Sla\SlaViolationService;
use Illuminate\Support\Facades\Log;

class CheckSlaViolationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sla:check-violations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for SLA response time violations and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(SlaViolationService $violationService): int
    {
        $this->info('Starting SLA violation check...');

        try {
            $violationService->checkResponseTimeViolations();
            $this->info('SLA violation check completed successfully.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error checking SLA violations: ' . $e->getMessage());
            Log::error('SLA violation check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
