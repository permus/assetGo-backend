<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\SlaDefinition;
use App\Models\WorkOrder;
use App\Models\WorkOrderCategory;
use App\Models\WorkOrderPriority;
use App\Models\WorkOrderStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ModuleDefinition;
use App\Models\CompanyModule;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SlaDefinitionModelTest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $user;
    protected $category;
    protected $priority;
    protected $status;

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
        $this->status = WorkOrderStatus::factory()->create([
            'company_id' => null,
            'slug' => 'open'
        ]);

        // Enable SLA module for the company
        $slaModule = ModuleDefinition::firstOrCreate(
            ['key' => 'sla'],
            [
                'display_name' => 'SLA',
                'description' => 'Service Level Agreement tracking and management',
                'icon_name' => 'sla',
                'route_path' => '/sla',
                'sort_order' => 55,
                'is_system_module' => false,
            ]
        );

        CompanyModule::create([
            'company_id' => $this->company->id,
            'module_id' => $slaModule->id,
            'is_enabled' => true,
        ]);
    }

    /** @test */
    public function it_matches_work_order_with_same_category_and_priority()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertTrue($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_does_not_match_work_order_with_different_category()
    {
        $otherCategory = WorkOrderCategory::factory()->create(['company_id' => null]);

        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $otherCategory->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertFalse($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_does_not_match_work_order_with_different_priority()
    {
        $otherPriority = WorkOrderPriority::factory()->create([
            'company_id' => null,
            'slug' => 'low'
        ]);

        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $otherPriority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertFalse($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_matches_work_order_when_category_is_null()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => null,
            'priority_level' => 'high',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => null,
            'priority_id' => $this->priority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertTrue($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_matches_work_order_when_priority_is_null()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => null,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertTrue($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_does_not_match_inactive_sla()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertFalse($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_does_not_match_when_applies_to_is_maintenance_only()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'maintenance',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertFalse($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_matches_when_applies_to_is_both()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'both',
            'category_id' => $this->category->id,
            'priority_level' => 'high',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertTrue($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_can_scope_active_definitions()
    {
        SlaDefinition::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        SlaDefinition::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        $activeCount = SlaDefinition::where('company_id', $this->company->id)->active()->count();
        $this->assertEquals(3, $activeCount);
    }

    /** @test */
    public function it_can_scope_for_work_orders()
    {
        SlaDefinition::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'created_by' => $this->user->id,
        ]);

        SlaDefinition::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'applies_to' => 'maintenance',
            'created_by' => $this->user->id,
        ]);

        SlaDefinition::factory()->count(1)->create([
            'company_id' => $this->company->id,
            'applies_to' => 'both',
            'created_by' => $this->user->id,
        ]);

        $forWorkOrdersCount = SlaDefinition::where('company_id', $this->company->id)->forWorkOrders()->count();
        $this->assertEquals(3, $forWorkOrdersCount); // 2 work_orders + 1 both
    }

    /** @test */
    public function it_can_scope_by_category()
    {
        $category1 = WorkOrderCategory::factory()->create(['company_id' => null]);
        $category2 = WorkOrderCategory::factory()->create(['company_id' => null]);

        SlaDefinition::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'category_id' => $category1->id,
            'created_by' => $this->user->id,
        ]);

        SlaDefinition::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'category_id' => $category2->id,
            'created_by' => $this->user->id,
        ]);

        $category1Count = SlaDefinition::where('company_id', $this->company->id)->byCategory($category1->id)->count();
        $this->assertEquals(2, $category1Count);

        $category2Count = SlaDefinition::where('company_id', $this->company->id)->byCategory($category2->id)->count();
        $this->assertEquals(3, $category2Count);
    }

    /** @test */
    public function it_handles_work_order_without_priority()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => $this->category->id,
            'priority_level' => null,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'priority_id' => null,
            'status_id' => $this->status->id,
        ]);

        $this->assertTrue($sla->matchesWorkOrder($workOrder));
    }

    /** @test */
    public function it_handles_work_order_without_category()
    {
        $sla = SlaDefinition::factory()->create([
            'company_id' => $this->company->id,
            'applies_to' => 'work_orders',
            'category_id' => null,
            'priority_level' => 'high',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => null,
            'priority_id' => $this->priority->id,
            'status_id' => $this->status->id,
        ]);

        $this->assertTrue($sla->matchesWorkOrder($workOrder));
    }
}

