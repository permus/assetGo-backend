<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;
use App\Models\Company;
use App\Models\User;
use App\Models\ReportRun;
use App\Jobs\ExportReportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

class ExportApiTest extends TestCase
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
        
        Queue::fake();
    }

    /** @test */
    public function it_can_request_export()
    {
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
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'run_id',
                        'status'
                    ]
                ])
                ->assertJson(['success' => true]);

        // Verify job was dispatched
        Queue::assertPushed(ExportReportJob::class);
    }

    /** @test */
    public function it_validates_export_request()
    {
        $response = $this->postJson('/api/reports/export', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['report_key', 'format']);
    }

    /** @test */
    public function it_validates_export_format()
    {
        $exportData = [
            'report_key' => 'assets.summary',
            'format' => 'invalid_format',
            'params' => []
        ];

        $response = $this->postJson('/api/reports/export', $exportData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['format']);
    }

    /** @test */
    public function it_can_get_export_status()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'success',
            'file_path' => 'reports/test-file.xlsx'
        ]);

        $response = $this->getJson("/api/reports/runs/{$reportRun->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'status',
                        'report_key',
                        'format',
                        'row_count',
                        'download_url',
                        'created_at',
                        'completed_at'
                    ]
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_export()
    {
        $response = $this->getJson('/api/reports/runs/999');

        $response->assertStatus(404)
                ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_returns_404_for_other_company_export()
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        
        $reportRun = ReportRun::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson("/api/reports/runs/{$reportRun->id}");

        $response->assertStatus(404)
                ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_can_get_export_history()
    {
        // Create test report runs
        ReportRun::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/reports/history');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'rows' => [],
                        'pagination' => []
                    ]
                ])
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_filter_export_history_by_status()
    {
        ReportRun::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'success'
        ]);
        
        ReportRun::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'failed'
        ]);

        $response = $this->getJson('/api/reports/history?' . http_build_query([
            'status' => 'success'
        ]));

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_filter_export_history_by_report_key()
    {
        ReportRun::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'report_key' => 'assets.summary'
        ]);
        
        ReportRun::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'report_key' => 'maintenance.summary'
        ]);

        $response = $this->getJson('/api/reports/history?' . http_build_query([
            'report_key' => 'assets'
        ]));

        $response->assertStatus(200)
                ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_can_cancel_export()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'queued'
        ]);

        $response = $this->deleteJson("/api/reports/runs/{$reportRun->id}/cancel");

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $reportRun->refresh();
        $this->assertEquals('cancelled', $reportRun->status);
    }

    /** @test */
    public function it_cannot_cancel_completed_export()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'success'
        ]);

        $response = $this->deleteJson("/api/reports/runs/{$reportRun->id}/cancel");

        $response->assertStatus(400)
                ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_cannot_cancel_other_company_export()
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        
        $reportRun = ReportRun::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'status' => 'queued'
        ]);

        $response = $this->deleteJson("/api/reports/runs/{$reportRun->id}/cancel");

        $response->assertStatus(404)
                ->assertJson(['success' => false]);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_export()
    {
        $exportData = [
            'report_key' => 'assets.summary',
            'format' => 'xlsx',
            'params' => []
        ];

        // Make multiple export requests quickly
        for ($i = 0; $i < 12; $i++) {
            $response = $this->postJson('/api/reports/export', $exportData);
            
            if ($i < 10) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429);
            }
        }
    }

    /** @test */
    public function it_handles_export_job_failures()
    {
        $exportData = [
            'report_key' => 'invalid.report',
            'format' => 'xlsx',
            'params' => []
        ];

        $response = $this->postJson('/api/reports/export', $exportData);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        // Verify job was still dispatched (error handling happens in job)
        Queue::assertPushed(ExportReportJob::class);
    }

    /** @test */
    public function it_requires_authentication()
    {
        auth()->logout();

        $response = $this->postJson('/api/reports/export', [
            'report_key' => 'assets.summary',
            'format' => 'xlsx'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_paginates_export_history()
    {
        ReportRun::factory()->count(25)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/reports/history?' . http_build_query([
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
    public function it_returns_consistent_response_structure()
    {
        $exportData = [
            'report_key' => 'assets.summary',
            'format' => 'xlsx',
            'params' => []
        ];

        $response = $this->postJson('/api/reports/export', $exportData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'run_id',
                        'status'
                    ]
                ]);
    }
}
