<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SlaDefinition;
use App\Models\Company;
use App\Models\User;

class SlaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Please run CompanySeeder first.');
            return;
        }

        $this->command->info('Seeding SLA definitions...');

        foreach ($companies as $company) {
            $users = User::where('company_id', $company->id)->get();
            $creator = $users->first() ?? User::where('company_id', $company->id)->first();

            if (!$creator) {
                continue;
            }

            // Create "Aya Mahmoud" SLA - Maintenance
            SlaDefinition::create([
                'company_id' => $company->id,
                'name' => 'Aya Mahmoud',
                'description' => null,
                'applies_to' => 'maintenance',
                'priority_level' => null,
                'category' => null,
                'response_time_hours' => 4,
                'containment_time_hours' => null,
                'completion_time_hours' => 24,
                'business_hours_only' => true,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_active' => true,
                'created_by' => $creator->id,
            ]);

            // Create "Emergency SLA" - Work Orders, Critical
            SlaDefinition::create([
                'company_id' => $company->id,
                'name' => 'Emergency SLA',
                'description' => 'Emergency SLA',
                'applies_to' => 'work_orders',
                'priority_level' => 'critical',
                'category' => null,
                'response_time_hours' => 2,
                'containment_time_hours' => 4,
                'completion_time_hours' => 6,
                'business_hours_only' => false,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'is_active' => true,
                'created_by' => $creator->id,
            ]);

            // Create additional sample SLAs
            SlaDefinition::create([
                'company_id' => $company->id,
                'name' => 'Standard Work Order Response',
                'description' => 'Standard SLA for regular work orders',
                'applies_to' => 'work_orders',
                'priority_level' => 'medium',
                'category' => 'General',
                'response_time_hours' => 8,
                'containment_time_hours' => null,
                'completion_time_hours' => 48,
                'business_hours_only' => true,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_active' => true,
                'created_by' => $creator->id,
            ]);

            SlaDefinition::create([
                'company_id' => $company->id,
                'name' => 'HVAC Maintenance SLA',
                'description' => 'SLA for HVAC maintenance tasks',
                'applies_to' => 'maintenance',
                'priority_level' => 'high',
                'category' => 'HVAC',
                'response_time_hours' => 6,
                'containment_time_hours' => 12,
                'completion_time_hours' => 36,
                'business_hours_only' => false,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                'is_active' => true,
                'created_by' => $creator->id,
            ]);
        }

        $this->command->info('SLA definitions seeded successfully.');
    }
}
