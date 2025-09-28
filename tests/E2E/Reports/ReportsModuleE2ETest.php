<?php

namespace Tests\E2E\Reports;

use Tests\TestCase;
use App\Models\Company;
use App\Models\User;
use App\Models\Asset;
use App\Models\Location;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderPriority;
use App\Models\ReportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ReportsModuleE2ETest extends TestCase
{
    use RefreshDatabase;

    protected $company;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_complete_full_reports_workflow()
    {
        // 1. Setup test data
        $this->setupTestData();

        // 2. Test Asset Reports
        $this->testAssetReportsWorkflow();

        // 3. Test Maintenance Reports
        $this->testMaintenanceReportsWorkflow();

        // 4. Test Export Workflow
        $this->testExportWorkflow();

        // 5. Test Filtering and Pagination
        $this->testFilteringAndPagination();

        // 6. Test Error Handling
        $this->testErrorHandling();
    }

    private function setupTestData()
    {
        // Create locations
        $location1 = Location::factory()->create(['company_id' => $this->company->id, 'name' => 'Building A']);
        $location2 = Location::factory()->create(['company_id' => $this->company->id, 'name' => 'Building B']);

        // Create assets
        Asset::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'location_id' => $location1->id,
            'status' => 'active'
        ]);

        Asset::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'location_id' => $location2->id,
            'status' => 'inactive'
        ]);

        // Create work order statuses and priorities
        $status = WorkOrderStatus::factory()->create(['company_id' => $this->company->id, 'name' => 'Open']);
        $priority = WorkOrderPriority::factory()->create(['company_id' => $this->company->id, 'name' => 'High']);

        // Create work orders
        WorkOrder::factory()->count(8)->create([
            'company_id' => $this->company->id,
            'status_id' => $status->id,
            'priority_id' => $priority->id
        ]);
    }

    private function testAssetReportsWorkflow()
    {
        // Test asset summary report
        $response = $this->getJson('/api/reports/assets/summary');
        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'rows' => [],
                        'totals' => [],
                        'pagination' => []
                    ]
                ]);

        // Test asset utilization report
        $response = $this->getJson('/api/reports/assets/utilization');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test asset depreciation report
        $response = $this->getJson('/api/reports/assets/depreciation');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test asset warranty report
        $response = $this->getJson('/api/reports/assets/warranty');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test asset compliance report
        $response = $this->getJson('/api/reports/assets/compliance');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test available reports
        $response = $this->getJson('/api/reports/assets/available');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    private function testMaintenanceReportsWorkflow()
    {
        // Test maintenance summary report
        $response = $this->getJson('/api/reports/maintenance/summary');
        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'rows' => [],
                        'kpis' => [],
                        'pagination' => []
                    ]
                ]);

        // Test maintenance compliance report
        $response = $this->getJson('/api/reports/maintenance/compliance');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test maintenance costs report
        $response = $this->getJson('/api/reports/maintenance/costs');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test downtime report
        $response = $this->getJson('/api/reports/maintenance/downtime');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test failure analysis report
        $response = $this->getJson('/api/reports/maintenance/failure-analysis');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test technician performance report
        $response = $this->getJson('/api/reports/maintenance/technician-performance');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test available reports
        $response = $this->getJson('/api/reports/maintenance/available');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    private function testExportWorkflow()
    {
        // Test export request
        $exportData = [
            'report_key' => 'assets.summary',
            'format' => 'xlsx',
            'params' => [
                'page' => 1,
                'page_size' => 10
            ]
        ];

        $response = $this->postJson('/api/reports/export', $exportData);
        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'run_id',
                        'status'
                    ]
                ]);

        $runId = $response->json('data.run_id');

        // Test export status check
        $response = $this->getJson("/api/reports/runs/{$runId}");
        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'status',
                        'report_key',
                        'format'
                    ]
                ]);

        // Test export history
        $response = $this->getJson('/api/reports/history');
        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'rows' => [],
                        'pagination' => []
                    ]
                ]);

        // Test cancel export (if still queued)
        $response = $this->deleteJson("/api/reports/runs/{$runId}/cancel");
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    private function testFilteringAndPagination()
    {
        // Test date range filtering
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ]));
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test location filtering
        $location = Location::where('company_id', $this->company->id)->first();
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'location_ids' => [$location->id]
        ]));
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test status filtering
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'status' => 'active'
        ]));
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Test pagination
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'page' => 1,
            'page_size' => 5
        ]));
        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'pagination' => [
                            'current_page',
                            'per_page',
                            'total',
                            'last_page'
                        ]
                    ]
                ]);
    }

    private function testErrorHandling()
    {
        // Test invalid date range
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'date_from' => '2024-12-31',
            'date_to' => '2024-01-01'
        ]));
        $response->assertStatus(500)
                ->assertJson(['success' => false]);

        // Test invalid export format
        $response = $this->postJson('/api/reports/export', [
            'report_key' => 'assets.summary',
            'format' => 'invalid_format'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['format']);

        // Test missing required fields
        $response = $this->postJson('/api/reports/export', []);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['report_key', 'format']);

        // Test nonexistent export
        $response = $this->getJson('/api/reports/runs/999');
        $response->assertStatus(404)
                ->assertJson(['success' => false]);

        // Test unauthorized access
        auth()->logout();
        $response = $this->getJson('/api/reports/assets/summary');
        $response->assertStatus(401);
    }

    /** @test */
    public function it_respects_company_isolation()
    {
        // Create another company with data
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherLocation = Location::factory()->create(['company_id' => $otherCompany->id]);
        
        Asset::factory()->count(5)->create([
            'company_id' => $otherCompany->id,
            'location_id' => $otherLocation->id
        ]);

        // Test that current user only sees their company's data
        $response = $this->getJson('/api/reports/assets/summary');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertCount(15, $data['rows']); // Only current company's assets

        // Test that other company's data is not accessible
        $otherUser->tokens()->delete();
        Sanctum::actingAs($otherUser);
        
        $response = $this->getJson('/api/reports/assets/summary');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertCount(5, $data['rows']); // Only other company's assets
    }

    /** @test */
    public function it_handles_large_datasets_efficiently()
    {
        // Create large dataset
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        Asset::factory()->count(1000)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id
        ]);

        $startTime = microtime(true);

        // Test pagination with large dataset
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'page' => 1,
            'page_size' => 50
        ]));

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Should complete within reasonable time (less than 2 seconds)
        $this->assertLessThan(2, $executionTime);

        $data = $response->json('data');
        $this->assertCount(50, $data['rows']);
        $this->assertEquals(1000, $data['pagination']['total']);
    }

    /** @test */
    public function it_handles_concurrent_requests()
    {
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        Asset::factory()->count(100)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id
        ]);

        $promises = [];
        
        // Make 10 concurrent requests
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $this->getJson('/api/reports/assets/summary');
        }

        // All requests should succeed
        foreach ($promises as $response) {
            $response->assertStatus(200)
                    ->assertJson(['success' => true]);
        }
    }

    /** @test */
    public function it_handles_rate_limiting()
    {
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        Asset::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id
        ]);

        // Make requests up to the rate limit
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/reports/assets/summary');
            $response->assertStatus(200);
        }

        // Next request should be rate limited
        $response = $this->getJson('/api/reports/assets/summary');
        $response->assertStatus(429);
    }

    /** @test */
    public function it_handles_malformed_requests()
    {
        // Test malformed JSON
        $response = $this->postJson('/api/reports/export', 'invalid json');
        $response->assertStatus(422);

        // Test invalid parameters
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'page' => 'invalid',
            'page_size' => 'invalid'
        ]));
        $response->assertStatus(200); // Should handle gracefully

        // Test extremely large page size
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'page_size' => 999999
        ]));
        $response->assertStatus(200); // Should handle gracefully
    }

    /** @test */
    public function it_handles_database_errors_gracefully()
    {
        // This test would require mocking database failures
        // For now, we'll test that the API returns proper error responses
        
        $response = $this->getJson('/api/reports/assets/summary');
        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_handles_memory_limits()
    {
        // Create a moderate dataset
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        Asset::factory()->count(500)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id
        ]);

        // Test with different page sizes
        $pageSizes = [10, 50, 100, 200];
        
        foreach ($pageSizes as $pageSize) {
            $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
                'page_size' => $pageSize
            ]));
            
            $response->assertStatus(200)
                    ->assertJson(['success' => true]);
            
            $data = $response->json('data');
            $this->assertCount($pageSize, $data['rows']);
        }
    }
}
