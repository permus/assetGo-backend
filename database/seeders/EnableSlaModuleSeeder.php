<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\ModuleDefinition;
use Illuminate\Database\Seeder;

class EnableSlaModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder ensures SLA module is defined and enabled for all companies.
     */
    public function run(): void
    {
        // Ensure SLA module definition exists
        $slaModule = ModuleDefinition::updateOrCreate(
            ['key' => 'sla'],
            [
                'display_name' => 'SLA',
                'description' => 'Service Level Agreement tracking and management',
                'icon_name' => 'sla',
                'route_path' => '/sla',
                'sort_order' => 55,
                'is_system_module' => false,
            ]
        );

        $this->command->info("SLA module definition ensured (ID: {$slaModule->id})");

        // Enable SLA module for all companies
        $companies = Company::all();
        $enabledCount = 0;

        foreach ($companies as $company) {
            $companyModule = CompanyModule::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'module_id' => $slaModule->id,
                ],
                [
                    'is_enabled' => true,
                ]
            );

            if ($companyModule->wasRecentlyCreated) {
                $enabledCount++;
            }
        }

        $this->command->info("SLA module enabled for {$enabledCount} companies.");
    }
}

