<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Http\Controllers\Api\AssetReportController;
use App\Services\AssetReportService;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Mockery;

class AssetReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $assetReportService;
    protected $company;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        
        $this->assetReportService = Mockery::mock(AssetReportService::class);
        $this->controller = new AssetReportController($this->assetReportService);
    }

    /** @test */
    public function it_can_get_asset_summary_report()
    {
        $expectedResponse = [
            'success' => true,
            'data' => [
                'rows' => [],
                'totals' => ['count' => 0],
                'pagination' => []
            ]
        ];

        $this->assetReportService
            ->shouldReceive('summary')
            ->once()
            ->andReturn($expectedResponse);

        $request = new Request();
        $response = $this->controller->summary($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    /** @test */
    public function it_can_get_asset_utilization_report()
    {
        $expectedResponse = [
            'success' => true,
            'data' => ['rows' => []]
        ];

        $this->assetReportService
            ->shouldReceive('utilization')
            ->once()
            ->andReturn($expectedResponse);

        $request = new Request();
        $response = $this->controller->utilization($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    /** @test */
    public function it_can_get_asset_depreciation_report()
    {
        $expectedResponse = [
            'success' => true,
            'data' => ['rows' => []]
        ];

        $this->assetReportService
            ->shouldReceive('depreciation')
            ->once()
            ->andReturn($expectedResponse);

        $request = new Request();
        $response = $this->controller->depreciation($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    /** @test */
    public function it_can_get_asset_warranty_report()
    {
        $expectedResponse = [
            'success' => true,
            'data' => ['rows' => []]
        ];

        $this->assetReportService
            ->shouldReceive('warranty')
            ->once()
            ->andReturn($expectedResponse);

        $request = new Request();
        $response = $this->controller->warranty($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    /** @test */
    public function it_can_get_asset_compliance_report()
    {
        $expectedResponse = [
            'success' => true,
            'data' => ['rows' => []]
        ];

        $this->assetReportService
            ->shouldReceive('compliance')
            ->once()
            ->andReturn($expectedResponse);

        $request = new Request();
        $response = $this->controller->compliance($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    /** @test */
    public function it_can_get_available_reports()
    {
        $expectedResponse = [
            'success' => true,
            'data' => []
        ];

        $this->assetReportService
            ->shouldReceive('getAvailableReports')
            ->once()
            ->andReturn($expectedResponse);

        $request = new Request();
        $response = $this->controller->available($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedResponse, $response->getData(true));
    }

    /** @test */
    public function it_handles_service_exceptions()
    {
        $this->assetReportService
            ->shouldReceive('summary')
            ->once()
            ->andThrow(new \Exception('Service error'));

        $request = new Request();
        $response = $this->controller->summary($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('Service error', $responseData['error']);
    }

    /** @test */
    public function it_passes_request_parameters_to_service()
    {
        $expectedResponse = [
            'success' => true,
            'data' => ['rows' => []]
        ];

        $this->assetReportService
            ->shouldReceive('summary')
            ->once()
            ->with(
                Mockery::on(function ($filters) {
                    return $filters['date_from'] === '2024-01-01' && 
                           $filters['date_to'] === '2024-12-31';
                }),
                1,
                50
            )
            ->andReturn($expectedResponse);

        $request = new Request([
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
            'page' => 1,
            'page_size' => 50
        ]);

        $response = $this->controller->summary($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_uses_default_pagination_values()
    {
        $expectedResponse = [
            'success' => true,
            'data' => ['rows' => []]
        ];

        $this->assetReportService
            ->shouldReceive('summary')
            ->once()
            ->with(
                Mockery::any(),
                1, // default page
                50 // default page_size
            )
            ->andReturn($expectedResponse);

        $request = new Request();
        $response = $this->controller->summary($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_filters_request_parameters_correctly()
    {
        $expectedResponse = [
            'success' => true,
            'data' => ['rows' => []]
        ];

        $this->assetReportService
            ->shouldReceive('summary')
            ->once()
            ->with(
                Mockery::on(function ($filters) {
                    return isset($filters['date_from']) && 
                           isset($filters['date_to']) &&
                           isset($filters['location_ids']) &&
                           isset($filters['status']) &&
                           !isset($filters['invalid_param']);
                }),
                Mockery::any(),
                Mockery::any()
            )
            ->andReturn($expectedResponse);

        $request = new Request([
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
            'location_ids' => [1, 2, 3],
            'status' => 'active',
            'invalid_param' => 'should_be_filtered',
            'page' => 1,
            'page_size' => 50
        ]);

        $response = $this->controller->summary($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
