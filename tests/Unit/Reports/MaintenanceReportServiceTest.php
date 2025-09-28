<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\MaintenanceReportService;
use App\Models\WorkOrder;
use App\Models\Company;
use App\Models\User;
use App\Models\Asset;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;

class MaintenanceReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $maintenanceReportService;
    protected $company;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        
        Auth::login($this->user);
        
        $this->maintenanceReportService = new MaintenanceReportService();
    }

    /** @test */
    public function it_can_generate_maintenance_summary_report()
    {
        // Create test data
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $status = WorkOrderStatus::factory()->create(['company_id' => $this->company->id]);
        $priority = WorkOrderPriority::factory()->create(['company_id' => $this->company->id]);
        
        WorkOrder::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status->id,
            'priority_id' => $priority->id
        ]);

        $filters = [
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ];

        $result = $this->maintenanceReportService->summary($filters, 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
        $this->assertArrayHasKey('kpis', $result['data']);
        $this->assertArrayHasKey('pagination', $result['data']);
        $this->assertCount(5, $result['data']['rows']);
    }

    /** @test */
    public function it_can_generate_maintenance_compliance_report()
    {
        $result = $this->maintenanceReportService->compliance([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_can_generate_maintenance_costs_report()
    {
        $result = $this->maintenanceReportService->costs([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_can_generate_downtime_report()
    {
        $result = $this->maintenanceReportService->downtime([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_can_generate_failure_analysis_report()
    {
        $result = $this->maintenanceReportService->failureAnalysis([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_can_generate_technician_performance_report()
    {
        $result = $this->maintenanceReportService->technicianPerformance([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_filters_work_orders_by_status()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $status1 = WorkOrderStatus::factory()->create(['company_id' => $this->company->id]);
        $status2 = WorkOrderStatus::factory()->create(['company_id' => $this->company->id]);
        $priority = WorkOrderPriority::factory()->create(['company_id' => $this->company->id]);
        
        WorkOrder::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status1->id,
            'priority_id' => $priority->id
        ]);
        
        WorkOrder::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status2->id,
            'priority_id' => $priority->id
        ]);

        $filters = ['status_id' => $status1->id];
        $result = $this->maintenanceReportService->summary($filters, 1, 10);

        $this->assertCount(3, $result['data']['rows']);
    }

    /** @test */
    public function it_filters_work_orders_by_priority()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $status = WorkOrderStatus::factory()->create(['company_id' => $this->company->id]);
        $priority1 = WorkOrderPriority::factory()->create(['company_id' => $this->company->id]);
        $priority2 = WorkOrderPriority::factory()->create(['company_id' => $this->company->id]);
        
        WorkOrder::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status->id,
            'priority_id' => $priority1->id
        ]);
        
        WorkOrder::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status->id,
            'priority_id' => $priority2->id
        ]);

        $filters = ['priority_id' => $priority1->id];
        $result = $this->maintenanceReportService->summary($filters, 1, 10);

        $this->assertCount(3, $result['data']['rows']);
    }

    /** @test */
    public function it_filters_work_orders_by_assigned_user()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $status = WorkOrderStatus::factory()->create(['company_id' => $this->company->id]);
        $priority = WorkOrderPriority::factory()->create(['company_id' => $this->company->id]);
        $user1 = User::factory()->create(['company_id' => $this->company->id]);
        $user2 = User::factory()->create(['company_id' => $this->company->id]);
        
        WorkOrder::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status->id,
            'priority_id' => $priority->id,
            'assigned_to' => $user1->id
        ]);
        
        WorkOrder::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status->id,
            'priority_id' => $priority->id,
            'assigned_to' => $user2->id
        ]);

        $filters = ['assigned_to' => $user1->id];
        $result = $this->maintenanceReportService->summary($filters, 1, 10);

        $this->assertCount(3, $result['data']['rows']);
    }

    /** @test */
    public function it_calculates_kpis_correctly()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $status = WorkOrderStatus::factory()->create(['company_id' => $this->company->id]);
        $priority = WorkOrderPriority::factory()->create(['company_id' => $this->company->id]);
        
        WorkOrder::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status->id,
            'priority_id' => $priority->id
        ]);

        $result = $this->maintenanceReportService->summary([], 1, 10);

        $this->assertArrayHasKey('kpis', $result['data']);
        $this->assertArrayHasKey('total_work_orders', $result['data']['kpis']);
        $this->assertEquals(10, $result['data']['kpis']['total_work_orders']);
    }

    /** @test */
    public function it_returns_available_reports()
    {
        $reports = $this->maintenanceReportService->getAvailableReports();

        $this->assertIsArray($reports);
        $this->assertCount(6, $reports);
        
        $expectedKeys = [
            'maintenance.summary',
            'maintenance.compliance',
            'maintenance.costs',
            'maintenance.downtime',
            'maintenance.failure-analysis',
            'maintenance.technician-performance'
        ];
        
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, array_column($reports, 'key'));
        }
    }

    /** @test */
    public function it_handles_empty_results()
    {
        $result = $this->maintenanceReportService->summary([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertCount(0, $result['data']['rows']);
        $this->assertEquals(0, $result['data']['kpis']['total_work_orders']);
    }

    /** @test */
    public function it_paginates_results_correctly()
    {
        $asset = Asset::factory()->create(['company_id' => $this->company->id]);
        $status = WorkOrderStatus::factory()->create(['company_id' => $this->company->id]);
        $priority = WorkOrderPriority::factory()->create(['company_id' => $this->company->id]);
        
        WorkOrder::factory()->count(25)->create([
            'company_id' => $this->company->id,
            'asset_id' => $asset->id,
            'status_id' => $status->id,
            'priority_id' => $priority->id
        ]);

        $result = $this->maintenanceReportService->summary([], 1, 10);

        $this->assertCount(10, $result['data']['rows']);
        $this->assertEquals(25, $result['data']['pagination']['total']);
        $this->assertEquals(3, $result['data']['pagination']['last_page']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
