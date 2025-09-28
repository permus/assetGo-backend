<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AssetReportService;
use App\Services\MaintenanceReportService;
use App\Services\ReportExportService;
use App\Models\ReportRun;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Exception;

class TestReportsModule extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reports:test 
                            {--user-id= : User ID to test with}
                            {--company-id= : Company ID to test with}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     */
    protected $description = 'Test the Reports Module functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Reports Module Tests');
        $this->line('================================');
        
        // Get test user and company
        $userId = $this->option('user-id');
        $companyId = $this->option('company-id');
        
        if (!$userId || !$companyId) {
            $this->error('Please provide both --user-id and --company-id options');
            return 1;
        }
        
        $user = User::find($userId);
        $company = Company::find($companyId);
        
        if (!$user || !$company) {
            $this->error('User or Company not found');
            return 1;
        }
        
        $this->info("Testing with User: {$user->name} ({$user->email})");
        $this->info("Company: {$company->name}");
        $this->line('');
        
        // Set up authentication context
        auth()->login($user);
        
        $testResults = [];
        
        // Test 1: Database Migrations
        $this->testDatabaseMigrations($testResults);
        
        // Test 2: Asset Reports
        $this->testAssetReports($testResults);
        
        // Test 3: Maintenance Reports
        $this->testMaintenanceReports($testResults);
        
        // Test 4: Export Functionality
        $this->testExportFunctionality($testResults);
        
        // Test 5: Error Handling
        $this->testErrorHandling($testResults);
        
        // Generate summary
        $this->generateSummary($testResults);
        
        return 0;
    }
    
    /**
     * Test database migrations
     */
    private function testDatabaseMigrations(array &$testResults)
    {
        $this->info('🗄️  Testing Database Migrations...');
        
        try {
            // Check if tables exist
            $tables = ['report_runs', 'report_templates', 'report_schedules'];
            $missingTables = [];
            
            foreach ($tables as $table) {
                if (!DB::getSchemaBuilder()->hasTable($table)) {
                    $missingTables[] = $table;
                }
            }
            
            if (empty($missingTables)) {
                $testResults['database_migrations'] = ['status' => 'PASS', 'message' => 'All tables created successfully'];
                $this->line('  ✅ All tables created successfully');
            } else {
                $testResults['database_migrations'] = ['status' => 'FAIL', 'message' => 'Missing tables: ' . implode(', ', $missingTables)];
                $this->line('  ❌ Missing tables: ' . implode(', ', $missingTables));
            }
            
            // Test model creation
            $run = new ReportRun();
            $template = new \App\Models\ReportTemplate();
            $schedule = new \App\Models\ReportSchedule();
            
            $testResults['model_creation'] = ['status' => 'PASS', 'message' => 'Models can be instantiated'];
            $this->line('  ✅ Models can be instantiated');
            
        } catch (Exception $e) {
            $testResults['database_migrations'] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->line('  ❌ Database test failed: ' . $e->getMessage());
        }
        
        $this->line('');
    }
    
    /**
     * Test Asset Reports
     */
    private function testAssetReports(array &$testResults)
    {
        $this->info('📊 Testing Asset Reports...');
        
        $assetService = app(AssetReportService::class);
        $reports = [
            'summary' => 'Asset Summary',
            'utilization' => 'Asset Utilization',
            'depreciation' => 'Asset Depreciation',
            'warranty' => 'Asset Warranty',
            'compliance' => 'Asset Compliance'
        ];
        
        foreach ($reports as $method => $name) {
            try {
                $result = $assetService->{"generate" . ucfirst($method)}([]);
                
                if (isset($result['success']) && $result['success'] === true) {
                    $testResults["asset_$method"] = ['status' => 'PASS', 'message' => 'Success'];
                    $this->line("  ✅ $name - Success");
                    
                    if ($this->option('verbose')) {
                        $this->line("    Data keys: " . implode(', ', array_keys($result['data'])));
                    }
                } else {
                    $testResults["asset_$method"] = ['status' => 'FAIL', 'message' => 'Invalid response format'];
                    $this->line("  ❌ $name - Invalid response format");
                }
            } catch (Exception $e) {
                $testResults["asset_$method"] = ['status' => 'FAIL', 'message' => $e->getMessage()];
                $this->line("  ❌ $name - Error: " . $e->getMessage());
            }
        }
        
        $this->line('');
    }
    
    /**
     * Test Maintenance Reports
     */
    private function testMaintenanceReports(array &$testResults)
    {
        $this->info('🔧 Testing Maintenance Reports...');
        
        $maintenanceService = app(MaintenanceReportService::class);
        $reports = [
            'summary' => 'Maintenance Summary',
            'compliance' => 'Maintenance Compliance',
            'costs' => 'Maintenance Costs',
            'downtime' => 'Downtime Analysis',
            'failureAnalysis' => 'Failure Analysis',
            'technicianPerformance' => 'Technician Performance'
        ];
        
        foreach ($reports as $method => $name) {
            try {
                $result = $maintenanceService->{"generate" . ucfirst($method)}([]);
                
                if (isset($result['success']) && $result['success'] === true) {
                    $testResults["maintenance_$method"] = ['status' => 'PASS', 'message' => 'Success'];
                    $this->line("  ✅ $name - Success");
                    
                    if ($this->option('verbose')) {
                        $this->line("    Data keys: " . implode(', ', array_keys($result['data'])));
                    }
                } else {
                    $testResults["maintenance_$method"] = ['status' => 'FAIL', 'message' => 'Invalid response format'];
                    $this->line("  ❌ $name - Invalid response format");
                }
            } catch (Exception $e) {
                $testResults["maintenance_$method"] = ['status' => 'FAIL', 'message' => $e->getMessage()];
                $this->line("  ❌ $name - Error: " . $e->getMessage());
            }
        }
        
        $this->line('');
    }
    
    /**
     * Test Export Functionality
     */
    private function testExportFunctionality(array &$testResults)
    {
        $this->info('📤 Testing Export Functionality...');
        
        try {
            // Test export service
            $exportService = app(ReportExportService::class);
            $companyId = auth()->user()->company_id;
            
            $stats = $exportService->getExportStatistics($companyId);
            $testResults['export_statistics'] = ['status' => 'PASS', 'message' => 'Statistics retrieved'];
            $this->line('  ✅ Export statistics retrieved');
            
            if ($this->option('verbose')) {
                $this->line("    Total exports: {$stats['total_exports']}");
                $this->line("    Success rate: {$stats['success_rate']}%");
            }
            
            // Test report run creation
            $reportRun = ReportRun::create([
                'company_id' => $companyId,
                'user_id' => auth()->id(),
                'report_key' => 'assets.summary',
                'params' => ['page' => 1, 'page_size' => 10],
                'format' => 'json',
                'status' => 'success',
                'row_count' => 5,
                'completed_at' => now()
            ]);
            
            if ($reportRun->id) {
                $testResults['report_run_creation'] = ['status' => 'PASS', 'message' => 'Report run created'];
                $this->line('  ✅ Report run created successfully');
                
                // Clean up
                $reportRun->delete();
            } else {
                $testResults['report_run_creation'] = ['status' => 'FAIL', 'message' => 'Failed to create report run'];
                $this->line('  ❌ Failed to create report run');
            }
            
        } catch (Exception $e) {
            $testResults['export_functionality'] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->line('  ❌ Export functionality test failed: ' . $e->getMessage());
        }
        
        $this->line('');
    }
    
    /**
     * Test Error Handling
     */
    private function testErrorHandling(array &$testResults)
    {
        $this->info('⚠️  Testing Error Handling...');
        
        try {
            // Test invalid report key
            $assetService = app(AssetReportService::class);
            
            try {
                $assetService->generateReport('invalid.report.key', []);
                $testResults['error_handling'] = ['status' => 'FAIL', 'message' => 'Should have thrown exception'];
                $this->line('  ❌ Invalid report key not handled properly');
            } catch (Exception $e) {
                $testResults['error_handling'] = ['status' => 'PASS', 'message' => 'Invalid report key properly handled'];
                $this->line('  ✅ Invalid report key properly handled');
            }
            
            // Test invalid date range
            try {
                $assetService->generateSummary(['date_from' => '2024-12-31', 'date_to' => '2024-01-01']);
                $testResults['date_validation'] = ['status' => 'FAIL', 'message' => 'Should have thrown exception'];
                $this->line('  ❌ Invalid date range not handled properly');
            } catch (Exception $e) {
                $testResults['date_validation'] = ['status' => 'PASS', 'message' => 'Invalid date range properly handled'];
                $this->line('  ✅ Invalid date range properly handled');
            }
            
        } catch (Exception $e) {
            $testResults['error_handling'] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $this->line('  ❌ Error handling test failed: ' . $e->getMessage());
        }
        
        $this->line('');
    }
    
    /**
     * Generate test summary
     */
    private function generateSummary(array $testResults)
    {
        $this->info('📋 Test Summary');
        $this->line('===============');
        
        $totalTests = count($testResults);
        $passedTests = count(array_filter($testResults, fn($result) => $result['status'] === 'PASS'));
        $failedTests = $totalTests - $passedTests;
        
        $this->line("Total Tests: $totalTests");
        $this->line("Passed: $passedTests");
        $this->line("Failed: $failedTests");
        
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
        $this->line("Success Rate: $successRate%");
        $this->line('');
        
        $this->line('Detailed Results:');
        $this->line('-----------------');
        
        foreach ($testResults as $testName => $result) {
            $status = $result['status'] === 'PASS' ? '✅' : '❌';
            $this->line("$status $testName: {$result['message']}");
        }
        
        if ($failedTests > 0) {
            $this->line('');
            $this->error('⚠️  Some tests failed. Please check the implementation.');
        } else {
            $this->line('');
            $this->info('🎉 All tests passed! The Reports Module is working correctly.');
        }
    }
}
