<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MaintenancePlan;
use App\Models\ScheduleMaintenance;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ExtendMaintenanceWorkOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $plan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create(['owner_id' => $this->user->id]);
        $this->user->update(['company_id' => $this->company->id]);

        // Create a work order status if it doesn't exist
        WorkOrderStatus::firstOrCreate(
            ['slug' => 'open'],
            ['name' => 'Open', 'is_management' => false, 'sort' => 1]
        );

        // Create a maintenance plan with time-based frequency
        $this->plan = MaintenancePlan::factory()->create([
            'company_id' => $this->company->id,
            'frequency_type' => 'time',
            'frequency_value' => 30,
            'frequency_unit' => 'days',
            'is_active' => true,
        ]);
    }

    public function test_command_extends_work_orders_when_last_is_within_3_months()
    {
        // Create a schedule
        $schedule = ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $this->plan->id,
            'start_date' => Carbon::now()->subMonths(2),
        ]);

        // Create a work order that's 2 months away (within 3 months threshold)
        $lastWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'due_date' => Carbon::now()->addMonths(2),
            'type' => 'ppm',
            'meta' => [
                'schedule_id' => $schedule->id,
                'plan_id' => $this->plan->id,
                'auto_generated' => true,
            ],
        ]);

        $schedule->update(['auto_generated_wo_ids' => [$lastWorkOrder->id]]);

        // Count work orders before
        $countBefore = WorkOrder::where('meta->schedule_id', $schedule->id)->count();

        // Run the command
        $exitCode = Artisan::call('maintenance:extend-work-orders', [
            '--schedule-id' => $schedule->id,
        ]);

        $this->assertEquals(0, $exitCode);

        // Count work orders after
        $countAfter = WorkOrder::where('meta->schedule_id', $schedule->id)->count();

        // Should have generated new work orders
        $this->assertGreaterThan($countBefore, $countAfter);
    }

    public function test_command_skips_schedule_when_last_work_order_is_more_than_3_months_away()
    {
        // Create a schedule
        $schedule = ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $this->plan->id,
        ]);

        // Create a work order that's 4 months away (outside 3 months threshold)
        $lastWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'due_date' => Carbon::now()->addMonths(4),
            'type' => 'ppm',
            'meta' => [
                'schedule_id' => $schedule->id,
                'plan_id' => $this->plan->id,
                'auto_generated' => true,
            ],
        ]);

        $countBefore = WorkOrder::where('meta->schedule_id', $schedule->id)->count();

        // Run the command
        $exitCode = Artisan::call('maintenance:extend-work-orders', [
            '--schedule-id' => $schedule->id,
        ]);

        $this->assertEquals(0, $exitCode);

        $countAfter = WorkOrder::where('meta->schedule_id', $schedule->id)->count();

        // Should not have generated new work orders
        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_command_skips_schedule_with_no_work_orders_unless_forced()
    {
        // Create a schedule with no work orders
        $schedule = ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $this->plan->id,
        ]);

        $countBefore = WorkOrder::where('meta->schedule_id', $schedule->id)->count();
        $this->assertEquals(0, $countBefore);

        // Run the command without force
        $exitCode = Artisan::call('maintenance:extend-work-orders', [
            '--schedule-id' => $schedule->id,
        ]);

        $this->assertEquals(0, $exitCode);

        $countAfter = WorkOrder::where('meta->schedule_id', $schedule->id)->count();

        // Should not have generated work orders
        $this->assertEquals(0, $countAfter);
    }

    public function test_command_generates_work_orders_when_forced()
    {
        // Create a schedule with no work orders
        $schedule = ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $this->plan->id,
            'start_date' => Carbon::now(),
        ]);

        // Run the command with force
        $exitCode = Artisan::call('maintenance:extend-work-orders', [
            '--schedule-id' => $schedule->id,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $countAfter = WorkOrder::where('meta->schedule_id', $schedule->id)->count();

        // Should have generated work orders
        $this->assertGreaterThan(0, $countAfter);
    }

    public function test_dry_run_mode_does_not_create_work_orders()
    {
        // Create a schedule
        $schedule = ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $this->plan->id,
            'start_date' => Carbon::now()->subMonths(2),
        ]);

        // Create a work order that's 2 months away
        $lastWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'due_date' => Carbon::now()->addMonths(2),
            'type' => 'ppm',
            'meta' => [
                'schedule_id' => $schedule->id,
                'plan_id' => $this->plan->id,
                'auto_generated' => true,
            ],
        ]);

        $countBefore = WorkOrder::where('meta->schedule_id', $schedule->id)->count();

        // Run the command in dry-run mode
        $exitCode = Artisan::call('maintenance:extend-work-orders', [
            '--schedule-id' => $schedule->id,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        $countAfter = WorkOrder::where('meta->schedule_id', $schedule->id)->count();

        // Should not have created any work orders
        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_command_avoids_duplicate_work_orders()
    {
        // Create a schedule
        $schedule = ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $this->plan->id,
            'start_date' => Carbon::now()->subMonths(2),
        ]);

        // Create a work order for a specific date
        $existingDate = Carbon::now()->addMonths(3);
        $existingWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'due_date' => $existingDate,
            'type' => 'ppm',
            'meta' => [
                'schedule_id' => $schedule->id,
                'plan_id' => $this->plan->id,
                'auto_generated' => true,
            ],
        ]);

        $schedule->update(['auto_generated_wo_ids' => [$existingWorkOrder->id]]);

        // Run the command
        $exitCode = Artisan::call('maintenance:extend-work-orders', [
            '--schedule-id' => $schedule->id,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Check that no duplicate was created for the same date
        $duplicates = WorkOrder::where('meta->schedule_id', $schedule->id)
            ->whereDate('due_date', $existingDate->toDateString())
            ->count();

        $this->assertEquals(1, $duplicates, 'Should not create duplicate work orders for the same date');
    }

    public function test_command_only_processes_time_based_plans()
    {
        // Create a non-time-based plan
        $nonTimePlan = MaintenancePlan::factory()->create([
            'company_id' => $this->company->id,
            'frequency_type' => 'usage',
            'is_active' => true,
        ]);

        $schedule = ScheduleMaintenance::factory()->create([
            'maintenance_plan_id' => $nonTimePlan->id,
        ]);

        // Run the command
        $exitCode = Artisan::call('maintenance:extend-work-orders');

        $this->assertEquals(0, $exitCode);

        // Should not have created any work orders for non-time-based plan
        $count = WorkOrder::where('meta->schedule_id', $schedule->id)->count();
        $this->assertEquals(0, $count);
    }
}

