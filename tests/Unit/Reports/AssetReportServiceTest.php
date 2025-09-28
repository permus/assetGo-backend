<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\AssetReportService;
use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;

class AssetReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $assetReportService;
    protected $company;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        
        Auth::login($this->user);
        
        $this->assetReportService = new AssetReportService();
    }

    /** @test */
    public function it_can_generate_asset_summary_report()
    {
        // Create test data
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        Asset::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id,
            'status' => 'active'
        ]);

        $filters = [
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31'
        ];

        $result = $this->assetReportService->summary($filters, 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
        $this->assertArrayHasKey('totals', $result['data']);
        $this->assertArrayHasKey('pagination', $result['data']);
        $this->assertCount(5, $result['data']['rows']);
    }

    /** @test */
    public function it_can_generate_asset_utilization_report()
    {
        $result = $this->assetReportService->utilization([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_can_generate_asset_depreciation_report()
    {
        $result = $this->assetReportService->depreciation([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_can_generate_asset_warranty_report()
    {
        $result = $this->assetReportService->warranty([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_can_generate_asset_compliance_report()
    {
        $result = $this->assetReportService->compliance([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
    }

    /** @test */
    public function it_filters_assets_by_location()
    {
        $location1 = Location::factory()->create(['company_id' => $this->company->id]);
        $location2 = Location::factory()->create(['company_id' => $this->company->id]);
        
        Asset::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'location_id' => $location1->id
        ]);
        
        Asset::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'location_id' => $location2->id
        ]);

        $filters = ['location_ids' => [$location1->id]];
        $result = $this->assetReportService->summary($filters, 1, 10);

        $this->assertCount(3, $result['data']['rows']);
    }

    /** @test */
    public function it_filters_assets_by_status()
    {
        Asset::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status' => 'active'
        ]);
        
        Asset::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'inactive'
        ]);

        $filters = ['status' => 'active'];
        $result = $this->assetReportService->summary($filters, 1, 10);

        $this->assertCount(3, $result['data']['rows']);
    }

    /** @test */
    public function it_validates_date_range()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $filters = [
            'date_from' => '2024-12-31',
            'date_to' => '2024-01-01'
        ];

        $this->assetReportService->summary($filters, 1, 10);
    }

    /** @test */
    public function it_returns_available_reports()
    {
        $reports = $this->assetReportService->getAvailableReports();

        $this->assertIsArray($reports);
        $this->assertCount(5, $reports);
        
        $expectedKeys = ['assets.summary', 'assets.utilization', 'assets.depreciation', 'assets.warranty', 'assets.compliance'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, array_column($reports, 'key'));
        }
    }

    /** @test */
    public function it_tracks_performance_metrics()
    {
        // Mock the performance tracking
        $this->app->instance('log', Mockery::mock('Psr\Log\LoggerInterface'));
        
        $result = $this->assetReportService->summary([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('generated_at', $result['meta']);
    }

    /** @test */
    public function it_handles_empty_results()
    {
        $result = $this->assetReportService->summary([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertCount(0, $result['data']['rows']);
        $this->assertEquals(0, $result['data']['totals']['count']);
    }

    /** @test */
    public function it_paginates_results_correctly()
    {
        Asset::factory()->count(25)->create(['company_id' => $this->company->id]);

        $result = $this->assetReportService->summary([], 1, 10);

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
