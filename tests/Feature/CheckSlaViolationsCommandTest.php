<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SlaDefinition;
use App\Models\WorkOrder;
use App\Models\WorkOrderCategory;
use App\Models\WorkOrderPriority;
use App\Models\WorkOrderStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\Sla\SlaViolationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class CheckSlaViolationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $user;
    protected $category;
    protected $priority;
    protected $openStatus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->category = WorkOrderCategory::factory()->create(['company_id' => null]);
        $this->priority = WorkOrderPriority::factory()->create([
            'company_id' => null,
            'slug' => 'high'
        ]);
        $this->openStatus = WorkOrderStatus::factory()->create([
            'company_id' => null,
            'slug' => 'open'
        ]);
    }

    /** @test */
    public function it_executes_command_successfully()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'response_time_hours' => 2,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $exitCode = Artisan::call('sla:check-violations');

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_logs_violations_found()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'response_time_hours' => 2,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        Artisan::call('sla:check-violations');

        // Verify violation was recorded
        $this->assertDatabaseHas('work_order_sla_violations', [
            'work_order_id' => $workOrder->id,
            'sla_definition_id' => $sla->id,
            'violation_type' => 'response_time',
        ]);
    }

    /** @test */
    public function it_handles_errors_gracefully()
    {
        // Create a scenario that might cause an error
        // For example, SLA with invalid data or missing relationships
        
        // Mock the service to throw an exception
        $this->mock(SlaViolationService::class, function ($mock) {
            $mock->shouldReceive('checkResponseTimeViolations')
                 ->andThrow(new \Exception('Test error'));
        });

        // Command should still complete without crashing
        $exitCode = Artisan::call('sla:check-violations');

        // Exit code should indicate failure
        $this->assertEquals(1, $exitCode);
    }

    /** @test */
    public function it_returns_success_exit_code()
    {
        // No violations scenario
        $exitCode = Artisan::call('sla:check-violations');

        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_processes_multiple_slas()
    {
        $sla1 = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'response_time_hours' => 2,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $category2 = WorkOrderCategory::factory()->create(['company_id' => null]);
        $sla2 = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $category2->id,
            'priority_level' => 'low',
            'response_time_hours' => 4,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder1 = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $priorityLow = WorkOrderPriority::firstOrCreate(
            ['slug' => 'low', 'company_id' => null],
            [
                'name' => 'Low',
                'is_management' => false,
                'sort' => 1,
            ]
        );

        $workOrder2 = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category2->id,
            'priority_id' => $priorityLow->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(5),
        ]);

        Artisan::call('sla:check-violations');

        // Both violations should be recorded
        $this->assertDatabaseHas('work_order_sla_violations', [
            'work_order_id' => $workOrder1->id,
            'sla_definition_id' => $sla1->id,
        ]);

        $this->assertDatabaseHas('work_order_sla_violations', [
            'work_order_id' => $workOrder2->id,
            'sla_definition_id' => $sla2->id,
        ]);
    }

    /** @test */
    public function it_skips_inactive_slas()
    {
        $inactiveSla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'response_time_hours' => 2,
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        Artisan::call('sla:check-violations');

        // Violation should not be recorded for inactive SLA
        $this->assertDatabaseMissing('work_order_sla_violations', [
            'work_order_id' => $workOrder->id,
            'sla_definition_id' => $inactiveSla->id,
        ]);
    }
}

