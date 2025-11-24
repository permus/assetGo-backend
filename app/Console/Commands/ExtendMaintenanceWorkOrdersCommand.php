<?php

namespace App\Console\Commands;

use App\Models\ScheduleMaintenance;
use App\Models\WorkOrder;
use App\Services\Maintenance\WorkOrderGenerationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExtendMaintenanceWorkOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'maintenance:extend-work-orders
                            {--schedule-id= : Process only a specific schedule ID}
                            {--force : Force regeneration even if work orders exist}
                            {--dry-run : Show what would be done without executing}';

    /**
     * The console command description.
     */
    protected $description = 'Extend work orders for maintenance schedules to maintain 12-month rolling window';

    /**
     * Execute the console command.
     */
    public function handle(WorkOrderGenerationService $service): int
    {
        $scheduleId = $this->option('schedule-id');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Query schedules
        $query = ScheduleMaintenance::with(['plan'])
            ->whereHas('plan', function($q) {
                $q->where('frequency_type', 'time')
                  ->where('is_active', true);
            });

        if ($scheduleId) {
            $query->where('id', $scheduleId);
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->warn('No active maintenance schedules found.');
            return self::SUCCESS;
        }

        $this->info("Found {$schedules->count()} schedule(s) to process.");
        $this->newLine();

        $stats = [
            'processed' => 0,
            'extended' => 0,
            'skipped' => 0,
            'errors' => 0,
            'work_orders_generated' => 0,
        ];

        $bar = $this->output->createProgressBar($schedules->count());
        $bar->start();

        foreach ($schedules as $schedule) {
            try {
                $result = $this->processSchedule($schedule, $service, $force, $dryRun);
                
                $stats['processed']++;
                if ($result['extended']) {
                    $stats['extended']++;
                    $stats['work_orders_generated'] += $result['count'];
                } else {
                    $stats['skipped']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                $stats['processed']++;
                
                Log::error('Failed to extend work orders for schedule', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
                
                if (!$dryRun) {
                    $this->newLine();
                    $this->error("Error processing schedule #{$schedule->id}: " . $e->getMessage());
                }
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display summary
        $this->displaySummary($stats, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Process a single schedule
     */
    protected function processSchedule(
        ScheduleMaintenance $schedule,
        WorkOrderGenerationService $service,
        bool $force,
        bool $dryRun
    ): array {
        $plan = $schedule->plan;
        
        if (!$plan || $plan->frequency_type !== 'time') {
            return ['extended' => false, 'count' => 0];
        }

        // Find the latest work order for this schedule
        $lastWorkOrder = WorkOrder::where('meta->schedule_id', $schedule->id)
            ->orderBy('due_date', 'desc')
            ->first();

        // If no work orders exist and not forcing, skip
        if (!$lastWorkOrder && !$force) {
            if ($this->option('verbose')) {
                $this->newLine();
                $this->line("Schedule #{$schedule->id}: No existing work orders found. Use --force to generate initial work orders.");
            }
            return ['extended' => false, 'count' => 0];
        }

        // Determine start date
        if ($lastWorkOrder) {
            $startFromDate = Carbon::parse($lastWorkOrder->due_date);
        } else {
            // No work orders exist, use schedule start_date or now
            $startFromDate = $schedule->start_date 
                ? Carbon::parse($schedule->start_date) 
                : Carbon::now();
        }

        // Check if extension is needed (last work order is within 3 months)
        if ($lastWorkOrder && !$force) {
            $monthsUntilLast = Carbon::now()->diffInMonths($lastWorkOrder->due_date, false);
            
            if ($monthsUntilLast > 3) {
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->line("Schedule #{$schedule->id}: Last work order is {$monthsUntilLast} months away. Skipping.");
                }
                return ['extended' => false, 'count' => 0];
            }
        }

        // Generate work orders
        if ($dryRun) {
            $dueDates = $this->calculateDueDates($plan, $startFromDate);
            $newDueDates = $this->filterExistingDueDates($schedule, $dueDates);
            
            $this->newLine();
            $this->info("Schedule #{$schedule->id} ({$plan->name}):");
            $this->line("  Would generate " . count($newDueDates) . " new work order(s)");
            $this->line("  Starting from: " . $startFromDate->format('Y-m-d'));
            if (!empty($newDueDates)) {
                $this->line("  First due date: " . $newDueDates[0]->format('Y-m-d'));
                $this->line("  Last due date: " . end($newDueDates)->format('Y-m-d'));
            }
            
            return ['extended' => count($newDueDates) > 0, 'count' => count($newDueDates)];
        }

        // Actually extend work orders
        $newWorkOrderIds = $service->extendWorkOrdersForSchedule($schedule, $startFromDate);
        
        return ['extended' => count($newWorkOrderIds) > 0, 'count' => count($newWorkOrderIds)];
    }

    /**
     * Calculate due dates for a plan starting from a specific date
     */
    protected function calculateDueDates($plan, Carbon $startDate): array
    {
        $dueDates = [];
        $currentDate = $startDate->copy();
        $endDate = Carbon::now()->addMonths(12);
        $maxOccurrences = 100;
        
        $value = (int)($plan->frequency_value ?? 0);
        $unit = $plan->frequency_unit;
        
        if ($value <= 0 || !$unit) {
            return [];
        }
        
        while ($currentDate->lte($endDate) && count($dueDates) < $maxOccurrences) {
            $nextDate = match ($unit) {
                'days' => $currentDate->copy()->addDays($value),
                'weeks' => $currentDate->copy()->addWeeks($value),
                'months' => $currentDate->copy()->addMonths($value),
                'years' => $currentDate->copy()->addYears($value),
                default => null,
            };
            
            if (!$nextDate) {
                break;
            }
            
            if ($nextDate->lte($endDate)) {
                $dueDates[] = $nextDate->copy();
            }
            $currentDate = $nextDate;
        }
        
        return $dueDates;
    }

    /**
     * Filter out due dates that already have work orders
     */
    protected function filterExistingDueDates(ScheduleMaintenance $schedule, array $dueDates): array
    {
        $newDueDates = [];
        
        foreach ($dueDates as $dueDate) {
            $exists = WorkOrder::where('meta->schedule_id', $schedule->id)
                ->whereDate('due_date', $dueDate->toDateString())
                ->exists();
            
            if (!$exists) {
                $newDueDates[] = $dueDate;
            }
        }
        
        return $newDueDates;
    }

    /**
     * Display summary statistics
     */
    protected function displaySummary(array $stats, bool $dryRun): void
    {
        $this->info('Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Schedules Processed', $stats['processed']],
                ['Schedules Extended', $stats['extended']],
                ['Schedules Skipped', $stats['skipped']],
                ['Work Orders Generated', $stats['work_orders_generated']],
                ['Errors', $stats['errors']],
            ]
        );

        if ($dryRun) {
            $this->warn('This was a dry run. No changes were made.');
        }
    }
}

