<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Services\ReportExportService;
use App\Models\ReportRun;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Mockery;

class ReportExportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $reportExportService;
    protected $company;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        
        Auth::login($this->user);
        
        $this->reportExportService = new ReportExportService();
    }

    /** @test */
    public function it_can_create_export_request()
    {
        $reportKey = 'assets.summary';
        $params = ['page' => 1, 'page_size' => 10];
        $format = 'xlsx';

        $reportRun = $this->reportExportService->export($reportKey, $params, $format);

        $this->assertInstanceOf(ReportRun::class, $reportRun);
        $this->assertEquals($this->company->id, $reportRun->company_id);
        $this->assertEquals($this->user->id, $reportRun->user_id);
        $this->assertEquals($reportKey, $reportRun->report_key);
        $this->assertEquals($format, $reportRun->format);
        $this->assertEquals('queued', $reportRun->status);
        $this->assertNotNull($reportRun->started_at);
    }

    /** @test */
    public function it_can_retrieve_report_run()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id
        ]);

        $retrieved = $this->reportExportService->getReportRun($reportRun->id);

        $this->assertInstanceOf(ReportRun::class, $retrieved);
        $this->assertEquals($reportRun->id, $retrieved->id);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_report_run()
    {
        $retrieved = $this->reportExportService->getReportRun(999);

        $this->assertNull($retrieved);
    }

    /** @test */
    public function it_returns_null_for_other_company_report_run()
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        
        $reportRun = ReportRun::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id
        ]);

        $retrieved = $this->reportExportService->getReportRun($reportRun->id);

        $this->assertNull($retrieved);
    }

    /** @test */
    public function it_can_generate_download_url_for_successful_export()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'success',
            'file_path' => 'reports/test-file.xlsx'
        ]);

        $downloadUrl = $this->reportExportService->getDownloadUrl($reportRun);

        $this->assertNotNull($downloadUrl);
        $this->assertStringContains('reports.download', $downloadUrl);
        $this->assertStringContains($reportRun->id, $downloadUrl);
    }

    /** @test */
    public function it_returns_null_for_unsuccessful_export()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'failed'
        ]);

        $downloadUrl = $this->reportExportService->getDownloadUrl($reportRun);

        $this->assertNull($downloadUrl);
    }

    /** @test */
    public function it_returns_null_for_export_without_file_path()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'success',
            'file_path' => null
        ]);

        $downloadUrl = $this->reportExportService->getDownloadUrl($reportRun);

        $this->assertNull($downloadUrl);
    }

    /** @test */
    public function it_can_get_export_history()
    {
        // Create test report runs
        ReportRun::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id
        ]);

        $result = $this->reportExportService->getExportHistory([], 1, 10);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('rows', $result['data']);
        $this->assertArrayHasKey('pagination', $result['data']);
        $this->assertCount(5, $result['data']['rows']);
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

        $filters = ['status' => 'success'];
        $result = $this->reportExportService->getExportHistory($filters, 1, 10);

        $this->assertCount(3, $result['data']['rows']);
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

        $filters = ['report_key' => 'assets'];
        $result = $this->reportExportService->getExportHistory($filters, 1, 10);

        $this->assertCount(3, $result['data']['rows']);
    }

    /** @test */
    public function it_can_cancel_queued_export()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'queued'
        ]);

        $result = $this->reportExportService->cancelExport($reportRun->id);

        $this->assertTrue($result);
        
        $reportRun->refresh();
        $this->assertEquals('cancelled', $reportRun->status);
        $this->assertStringContains('cancelled by user', $reportRun->error_message);
    }

    /** @test */
    public function it_can_cancel_running_export()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'running'
        ]);

        $result = $this->reportExportService->cancelExport($reportRun->id);

        $this->assertTrue($result);
        
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

        $result = $this->reportExportService->cancelExport($reportRun->id);

        $this->assertFalse($result);
        
        $reportRun->refresh();
        $this->assertEquals('success', $reportRun->status);
    }

    /** @test */
    public function it_cannot_cancel_failed_export()
    {
        $reportRun = ReportRun::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status' => 'failed'
        ]);

        $result = $this->reportExportService->cancelExport($reportRun->id);

        $this->assertFalse($result);
        
        $reportRun->refresh();
        $this->assertEquals('failed', $reportRun->status);
    }

    /** @test */
    public function it_cannot_cancel_nonexistent_export()
    {
        $result = $this->reportExportService->cancelExport(999);

        $this->assertFalse($result);
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

        $result = $this->reportExportService->cancelExport($reportRun->id);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_paginates_export_history_correctly()
    {
        ReportRun::factory()->count(25)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id
        ]);

        $result = $this->reportExportService->getExportHistory([], 1, 10);

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
