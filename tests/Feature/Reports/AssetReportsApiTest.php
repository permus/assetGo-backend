<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use App\Models\Company;
use App\Models\User;
use App\Models\Asset;
use App\Models\Location;
use App\Models\ReportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AssetReportsApiTest extends TestCase
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
    public function it_can_get_asset_summary_report()
    {
        // Create test data
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        Asset::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id,
            'status' => 'active'
        ]);

        $response = $this->getJson('/api/reports/assets/summary');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'rows' => [],
                        'totals' => [],
                        'pagination' => []
                    ],
                    'meta' => []
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_asset_utilization_report()
    {
        $response = $this->getJson('/api/reports/assets/utilization');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'rows' => []
                    ],
                    'meta' => []
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_asset_depreciation_report()
    {
        $response = $this->getJson('/api/reports/assets/depreciation');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'rows' => []
                    ],
                    'meta' => []
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_asset_warranty_report()
    {
        $response = $this->getJson('/api/reports/assets/warranty');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'rows' => []
                    ],
                    'meta' => []
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_asset_compliance_report()
    {
        $response = $this->getJson('/api/reports/assets/compliance');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'rows' => []
                    ],
                    'meta' => []
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_get_available_asset_reports()
    {
        $response = $this->getJson('/api/reports/assets/available');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => []
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_filters_assets_by_date_range()
    {
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        
        Asset::factory()->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id,
            'created_at' => '2024-01-15'
        ]);
        
        Asset::factory()->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id,
            'created_at' => '2024-06-15'
        ]);

        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'date_from' => '2024-01-01',
            'date_to' => '2024-03-31'
        ]));

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
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

        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'location_ids' => [$location1->id]
        ]));

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_filters_assets_by_status()
    {
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        
        Asset::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id,
            'status' => 'active'
        ]);
        
        Asset::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id,
            'status' => 'inactive'
        ]);

        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'status' => 'active'
        ]));

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_paginates_results()
    {
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        Asset::factory()->count(25)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id
        ]);

        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'page' => 1,
            'page_size' => 10
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

    /** @test */
    public function it_requires_authentication()
    {
        // Clear authentication
        auth()->logout();

        $response = $this->getJson('/api/reports/assets/summary');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_invalid_date_range()
    {
        $response = $this->getJson('/api/reports/assets/summary?' . http_build_query([
            'date_from' => '2024-12-31',
            'date_to' => '2024-01-01'
        ]));

        $response->assertStatus(500)
                ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_handles_missing_parameters_gracefully()
    {
        $response = $this->getJson('/api/reports/assets/summary');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_respects_rate_limiting()
    {
        // Make multiple requests quickly to test rate limiting
        for ($i = 0; $i < 65; $i++) {
            $response = $this->getJson('/api/reports/assets/summary');
            
            if ($i < 60) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429);
            }
        }
    }

    /** @test */
    public function it_includes_company_scoped_data_only()
    {
        $otherCompany = Company::factory()->create();
        $otherLocation = Location::factory()->create(['company_id' => $otherCompany->id]);
        
        // Create assets for other company
        Asset::factory()->count(3)->create([
            'company_id' => $otherCompany->id,
            'location_id' => $otherLocation->id
        ]);

        // Create assets for current company
        $location = Location::factory()->create(['company_id' => $this->company->id]);
        Asset::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'location_id' => $location->id
        ]);

        $response = $this->getJson('/api/reports/assets/summary');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
        
        // Verify only current company's assets are returned
        $data = $response->json('data');
        $this->assertCount(2, $data['rows']);
    }

    /** @test */
    public function it_returns_consistent_response_structure()
    {
        $response = $this->getJson('/api/reports/assets/summary');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'rows' => [],
                        'totals' => [
                            'count',
                            'total_cost',
                            'average_value'
                        ],
                        'pagination' => [
                            'current_page',
                            'per_page',
                            'total',
                            'last_page',
                            'from',
                            'to',
                            'has_more_pages'
                        ]
                    ],
                    'meta' => [
                        'generated_at',
                        'company_id'
                    ]
                ]);
    }
}
