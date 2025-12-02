<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\SlaDefinition;
use App\Models\WorkOrder;
use App\Models\WorkOrderSlaViolation;
use App\Models\WorkOrderCategory;
use App\Models\WorkOrderPriority;
use App\Models\WorkOrderStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\Sla\SlaViolationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SlaViolationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $user;
    protected $category;
    protected $priority;
    protected $openStatus;
    protected $completedStatus;
    protected $violationService;
    protected $notificationService;

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
        $this->openStatus = WorkOrderStatus::firstOrCreate(
            ['slug' => 'open', 'company_id' => null],
            [
                'name' => 'Open',
                'is_management' => false,
                'sort' => 1,
            ]
        );
        $this->completedStatus = WorkOrderStatus::firstOrCreate(
            ['slug' => 'completed', 'company_id' => null],
            [
                'name' => 'Completed',
                'is_management' => false,
                'sort' => 100,
            ]
        );

        $this->notificationService = $this->createMock(NotificationService::class);
        $this->violationService = new SlaViolationService($this->notificationService);
    }

    /** @test */
    public function it_detects_response_time_violation()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'response_time_hours' => 4,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        // Create work order that violates response time (created 5 hours ago)
        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(5),
        ]);

        $this->notificationService->expects($this->once())
            ->method('createForUsers')
            ->with(
                $this->callback(function ($userIds) use ($workOrder) {
                    return in_array($workOrder->created_by, $userIds);
                }),
                $this->callback(function ($data) use ($workOrder, $sla) {
                    return $data['type'] === 'sla_violation' &&
                           $data['data']['workOrderId'] === $workOrder->id &&
                           $data['data']['slaDefinitionId'] === $sla->id;
                })
            );

        $this->violationService->checkResponseTimeViolations();

        // Verify violation was recorded
        $this->assertDatabaseHas('work_order_sla_violations', [
            'work_order_id' => $workOrder->id,
            'sla_definition_id' => $sla->id,
            'violation_type' => 'response_time',
        ]);
    }

    /** @test */
    public function it_sends_notifications_on_violation()
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

        $createdBy = User::factory()->create(['company_id' => $this->company->id]);
        $assignedTo = User::factory()->create(['company_id' => $this->company->id]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $createdBy->id,
            'assigned_to' => $assignedTo->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $this->notificationService->expects($this->once())
            ->method('createForUsers')
            ->with(
                $this->callback(function ($userIds) use ($createdBy, $assignedTo) {
                    return in_array($createdBy->id, $userIds) &&
                           in_array($assignedTo->id, $userIds) &&
                           count($userIds) === 2;
                }),
                $this->anything()
            );

        $this->violationService->checkResponseTimeViolations();
    }

    /** @test */
    public function it_prevents_duplicate_notifications()
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

        // Create existing violation with notification sent
        WorkOrderSlaViolation::create([
            'work_order_id' => $workOrder->id,
            'sla_definition_id' => $sla->id,
            'violation_type' => 'response_time',
            'violated_at' => Carbon::now()->subHour(),
            'notified_at' => Carbon::now()->subHour(),
        ]);

        // Should not send notification again
        $this->notificationService->expects($this->never())
            ->method('createForUsers');

        $this->violationService->checkResponseTimeViolations();
    }

    /** @test */
    public function it_only_checks_active_slas()
    {
        $activeSla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'response_time_hours' => 2,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

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

        // Should only check active SLA
        $this->notificationService->expects($this->once())
            ->method('createForUsers');

        $this->violationService->checkResponseTimeViolations();

        // Only active SLA should have violation recorded
        $this->assertDatabaseHas('work_order_sla_violations', [
            'work_order_id' => $workOrder->id,
            'sla_definition_id' => $activeSla->id,
        ]);

        $this->assertDatabaseMissing('work_order_sla_violations', [
            'work_order_id' => $workOrder->id,
            'sla_definition_id' => $inactiveSla->id,
        ]);
    }

    /** @test */
    public function it_filters_by_category_and_priority()
    {
        $category1 = WorkOrderCategory::factory()->create(['company_id' => null]);
        $category2 = WorkOrderCategory::factory()->create(['company_id' => null]);
        $priorityLow = WorkOrderPriority::factory()->create([
            'company_id' => null,
            'slug' => 'low'
        ]);

        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $category1->id,
            'priority_level' => 'high',
            'response_time_hours' => 2,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        // Work order with matching category and priority
        $matchingWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category1->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        // Work order with different category
        $differentCategoryWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category2->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        // Work order with different priority
        $differentPriorityWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $category1->id,
            'priority_id' => $priorityLow->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $this->notificationService->expects($this->once())
            ->method('createForUsers');

        $this->violationService->checkResponseTimeViolations();

        // Only matching work order should have violation
        $this->assertDatabaseHas('work_order_sla_violations', [
            'work_order_id' => $matchingWorkOrder->id,
            'sla_definition_id' => $sla->id,
        ]);

        $this->assertDatabaseMissing('work_order_sla_violations', [
            'work_order_id' => $differentCategoryWorkOrder->id,
        ]);

        $this->assertDatabaseMissing('work_order_sla_violations', [
            'work_order_id' => $differentPriorityWorkOrder->id,
        ]);
    }

    /** @test */
    public function it_skips_completed_work_orders()
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

        $completedWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->completedStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $this->notificationService->expects($this->never())
            ->method('createForUsers');

        $this->violationService->checkResponseTimeViolations();

        $this->assertDatabaseMissing('work_order_sla_violations', [
            'work_order_id' => $completedWorkOrder->id,
        ]);
    }

    /** @test */
    public function it_skips_cancelled_work_orders()
    {
        $cancelledStatus = WorkOrderStatus::firstOrCreate(
            ['slug' => 'cancelled', 'company_id' => null],
            [
                'name' => 'Cancelled',
                'is_management' => false,
                'sort' => 100,
            ]
        );

        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'response_time_hours' => 2,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $cancelledWorkOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $cancelledStatus->id,
            'created_by' => $this->user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $this->notificationService->expects($this->never())
            ->method('createForUsers');

        $this->violationService->checkResponseTimeViolations();

        $this->assertDatabaseMissing('work_order_sla_violations', [
            'work_order_id' => $cancelledWorkOrder->id,
        ]);
    }

    /** @test */
    public function it_notifies_created_by_and_assigned_to()
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

        $createdBy = User::factory()->create(['company_id' => $this->company->id]);
        $assignedTo = User::factory()->create(['company_id' => $this->company->id]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $createdBy->id,
            'assigned_to' => $assignedTo->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $this->notificationService->expects($this->once())
            ->method('createForUsers')
            ->with(
                $this->callback(function ($userIds) use ($createdBy, $assignedTo) {
                    return count($userIds) === 2 &&
                           in_array($createdBy->id, $userIds) &&
                           in_array($assignedTo->id, $userIds);
                }),
                $this->anything()
            );

        $this->violationService->checkResponseTimeViolations();
    }

    /** @test */
    public function it_handles_missing_users_gracefully()
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

        // Work order with created_by but no assigned_to (simulating scenario where assigned user doesn't exist)
        // Note: created_by is required by database, so we test with assigned_to = null
        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->user->id,
            'assigned_to' => null, // No assigned user
            'created_at' => Carbon::now()->subHours(3),
        ]);

        // Should send notification to created_by user (not "never" since created_by exists)
        $this->notificationService->expects($this->once())
            ->method('createForUsers')
            ->with(
                $this->callback(function ($userIds) use ($workOrder) {
                    return count($userIds) === 1 && $userIds[0] === $workOrder->created_by;
                }),
                $this->anything()
            );

        // Should not throw exception
        try {
            $this->violationService->checkResponseTimeViolations();
        } catch (\Exception $e) {
            $this->fail('Service should handle missing assigned_to gracefully: ' . $e->getMessage());
        }

        // Violation should still be recorded
        $this->assertDatabaseHas('work_order_sla_violations', [
            'work_order_id' => $workOrder->id,
            'sla_definition_id' => $sla->id,
        ]);
    }

    /** @test */
    public function it_does_not_notify_same_user_twice()
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

        $user = User::factory()->create(['company_id' => $this->company->id]);

        // Work order where created_by and assigned_to are the same user
        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        $this->notificationService->expects($this->once())
            ->method('createForUsers')
            ->with(
                $this->callback(function ($userIds) use ($user) {
                    // Should only contain user once
                    return count($userIds) === 1 &&
                           $userIds[0] === $user->id;
                }),
                $this->anything()
            );

        $this->violationService->checkResponseTimeViolations();
    }
}

