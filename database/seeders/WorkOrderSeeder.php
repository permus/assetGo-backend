<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WorkOrder;
use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use App\Models\Company;

class WorkOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing companies
        $companies = Company::all();
        
        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Please run CompanySeeder first.');
            return;
        }

        foreach ($companies as $company) {
            // Get assets, locations, and users for this company
            $assets = Asset::where('company_id', $company->id)->get();
            $locations = Location::where('company_id', $company->id)->get();
            $users = User::where('company_id', $company->id)->get();

            if ($assets->isEmpty() || $users->isEmpty()) {
                $this->command->warn("Skipping work orders for company {$company->name} - missing assets or users.");
                continue;
            }

            // Create work orders for this company
            WorkOrder::factory()
                ->count(rand(5, 15))
                ->create([
                    'company_id' => $company->id,
                    'asset_id' => $assets->random()->id,
                    'location_id' => $locations->isNotEmpty() ? $locations->random()->id : null,
                    'assigned_to' => $users->random()->id,
                    'assigned_by' => $users->random()->id,
                    'created_by' => $users->random()->id,
                ]);

            // Create some specific work order types
            WorkOrder::factory()
                ->count(2)
                ->open()
                ->create([
                    'company_id' => $company->id,
                    'asset_id' => $assets->random()->id,
                    'assigned_to' => $users->random()->id,
                    'assigned_by' => $users->random()->id,
                    'created_by' => $users->random()->id,
                ]);

            WorkOrder::factory()
                ->count(1)
                ->inProgress()
                ->create([
                    'company_id' => $company->id,
                    'asset_id' => $assets->random()->id,
                    'assigned_to' => $users->random()->id,
                    'assigned_by' => $users->random()->id,
                    'created_by' => $users->random()->id,
                ]);

            WorkOrder::factory()
                ->count(4)
                ->completed()
                ->create([
                    'company_id' => $company->id,
                    'asset_id' => $assets->random()->id,
                    'assigned_to' => $users->random()->id,
                    'assigned_by' => $users->random()->id,
                    'created_by' => $users->random()->id,
                ]);

            WorkOrder::factory()
                ->count(1)
                ->overdue()
                ->create([
                    'company_id' => $company->id,
                    'asset_id' => $assets->random()->id,
                    'assigned_to' => $users->random()->id,
                    'assigned_by' => $users->random()->id,
                    'created_by' => $users->random()->id,
                ]);

            WorkOrder::factory()
                ->count(1)
                ->highPriority()
                ->create([
                    'company_id' => $company->id,
                    'asset_id' => $assets->random()->id,
                    'assigned_to' => $users->random()->id,
                    'assigned_by' => $users->random()->id,
                    'created_by' => $users->random()->id,
                ]);

            WorkOrder::factory()
                ->count(1)
                ->criticalPriority()
                ->create([
                    'company_id' => $company->id,
                    'asset_id' => $assets->random()->id,
                    'assigned_to' => $users->random()->id,
                    'assigned_by' => $users->random()->id,
                    'created_by' => $users->random()->id,
                ]);
        }

        $this->command->info('Work orders seeded successfully!');
    }
}
