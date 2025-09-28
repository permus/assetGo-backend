<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReportRun;
use App\Models\ReportTemplate;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Models\Company;
use Carbon\Carbon;

class ReportsTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first company and user for testing
        $company = Company::first();
        $user = User::first();
        
        if (!$company || !$user) {
            $this->command->warn('No company or user found. Please run other seeders first.');
            return;
        }
        
        $this->command->info('Creating test data for Reports Module...');
        
        // Create sample report templates
        $this->createReportTemplates($company, $user);
        
        // Create sample report runs
        $this->createReportRuns($company, $user);
        
        // Create sample report schedules
        $this->createReportSchedules($company, $user);
        
        $this->command->info('Reports test data created successfully!');
    }
    
    /**
     * Create sample report templates
     */
    private function createReportTemplates(Company $company, User $user): void
    {
        $templates = [
            [
                'name' => 'Monthly Asset Summary',
                'description' => 'Comprehensive monthly asset summary report',
                'report_key' => 'assets.summary',
                'definition' => [
                    'tables' => ['assets'],
                    'fields' => ['id', 'name', 'status', 'purchase_price', 'location_id'],
                    'filters' => ['date_from', 'date_to', 'status'],
                    'group_by' => 'status',
                    'order_by' => 'created_at'
                ],
                'default_filters' => [
                    'date_from' => now()->startOfMonth()->toDateString(),
                    'date_to' => now()->endOfMonth()->toDateString()
                ],
                'is_shared' => true,
                'is_public' => true
            ],
            [
                'name' => 'Asset Depreciation Report',
                'description' => 'Detailed asset depreciation analysis',
                'report_key' => 'assets.depreciation',
                'definition' => [
                    'tables' => ['assets'],
                    'fields' => ['id', 'name', 'purchase_price', 'purchase_date', 'depreciation_life'],
                    'filters' => ['date_from', 'date_to'],
                    'group_by' => null,
                    'order_by' => 'purchase_date'
                ],
                'default_filters' => [
                    'date_from' => now()->subYear()->toDateString(),
                    'date_to' => now()->toDateString()
                ],
                'is_shared' => true,
                'is_public' => false
            ],
            [
                'name' => 'Maintenance Performance',
                'description' => 'Technician performance and maintenance metrics',
                'report_key' => 'maintenance.technician_performance',
                'definition' => [
                    'tables' => ['work_orders', 'users'],
                    'fields' => ['id', 'title', 'assigned_to', 'status', 'created_at', 'completed_at'],
                    'filters' => ['date_from', 'date_to', 'assigned_to'],
                    'group_by' => 'assigned_to',
                    'order_by' => 'created_at'
                ],
                'default_filters' => [
                    'date_from' => now()->subMonth()->toDateString(),
                    'date_to' => now()->toDateString()
                ],
                'is_shared' => true,
                'is_public' => true
            ],
            [
                'name' => 'Custom Asset Analysis',
                'description' => 'Custom report for specific asset analysis needs',
                'report_key' => null, // Custom report
                'definition' => [
                    'tables' => ['assets', 'locations'],
                    'fields' => ['assets.id', 'assets.name', 'locations.name as location'],
                    'filters' => ['location_ids', 'asset_type'],
                    'group_by' => 'locations.name',
                    'order_by' => 'assets.name'
                ],
                'default_filters' => [],
                'is_shared' => false,
                'is_public' => false
            ]
        ];
        
        foreach ($templates as $templateData) {
            ReportTemplate::create(array_merge($templateData, [
                'company_id' => $company->id,
                'owner_id' => $user->id
            ]));
        }
        
        $this->command->info('Created ' . count($templates) . ' report templates');
    }
    
    /**
     * Create sample report runs
     */
    private function createReportRuns(Company $company, User $user): void
    {
        $reportKeys = [
            'assets.summary',
            'assets.utilization',
            'assets.depreciation',
            'assets.warranty',
            'maintenance.summary',
            'maintenance.compliance',
            'maintenance.costs'
        ];
        
        $formats = ['json', 'csv', 'xlsx', 'pdf'];
        $statuses = ['success', 'failed', 'queued', 'running'];
        
        for ($i = 0; $i < 20; $i++) {
            $reportKey = $reportKeys[array_rand($reportKeys)];
            $format = $formats[array_rand($formats)];
            $status = $statuses[array_rand($statuses)];
            
            $startedAt = now()->subDays(rand(1, 30))->subHours(rand(0, 23));
            $completedAt = null;
            $executionTime = null;
            
            if (in_array($status, ['success', 'failed'])) {
                $completedAt = $startedAt->copy()->addMinutes(rand(1, 30));
                $executionTime = $startedAt->diffInMilliseconds($completedAt);
            }
            
            ReportRun::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'report_key' => $reportKey,
                'params' => [
                    'page' => 1,
                    'page_size' => 50,
                    'date_from' => now()->subMonth()->toDateString(),
                    'date_to' => now()->toDateString()
                ],
                'filters' => [
                    'date_from' => now()->subMonth()->toDateString(),
                    'date_to' => now()->toDateString()
                ],
                'format' => $format,
                'status' => $status,
                'row_count' => $status === 'success' ? rand(10, 1000) : 0,
                'file_path' => $status === 'success' ? "reports/test-{$i}.{$format}" : null,
                'error_message' => $status === 'failed' ? 'Test error message' : null,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'execution_time_ms' => $executionTime
            ]);
        }
        
        $this->command->info('Created 20 sample report runs');
    }
    
    /**
     * Create sample report schedules
     */
    private function createReportSchedules(Company $company, User $user): void
    {
        $templates = ReportTemplate::where('company_id', $company->id)->get();
        
        if ($templates->isEmpty()) {
            $this->command->warn('No templates found. Skipping schedule creation.');
            return;
        }
        
        $schedules = [
            [
                'name' => 'Daily Asset Summary',
                'description' => 'Daily asset summary report',
                'rrule' => 'FREQ=DAILY;BYHOUR=8',
                'timezone' => 'UTC',
                'delivery_email' => 'admin@example.com',
                'enabled' => true
            ],
            [
                'name' => 'Weekly Maintenance Report',
                'description' => 'Weekly maintenance performance report',
                'rrule' => 'FREQ=WEEKLY;BYDAY=MON;BYHOUR=9',
                'timezone' => 'UTC',
                'delivery_email' => 'manager@example.com,admin@example.com',
                'enabled' => true
            ],
            [
                'name' => 'Monthly Depreciation Report',
                'description' => 'Monthly asset depreciation analysis',
                'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=1;BYHOUR=10',
                'timezone' => 'UTC',
                'delivery_email' => 'finance@example.com',
                'enabled' => false
            ]
        ];
        
        foreach ($schedules as $index => $scheduleData) {
            $template = $templates->get($index % $templates->count());
            
            ReportSchedule::create(array_merge($scheduleData, [
                'company_id' => $company->id,
                'template_id' => $template->id,
                'last_run_at' => now()->subDays(rand(1, 7)),
                'next_run_at' => now()->addDays(rand(1, 7))
            ]));
        }
        
        $this->command->info('Created ' . count($schedules) . ' report schedules');
    }
}
