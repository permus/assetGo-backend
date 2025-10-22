<?php

namespace Database\Seeders;

use App\Models\PredictiveMaintenance;
use App\Models\Company;
use App\Models\Asset;
use Illuminate\Database\Seeder;

class PredictiveMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (PredictiveMaintenance::where('company_id', $company->id)->count() >= 20) {
            $this->command->info('Predictive maintenance records already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding predictive maintenance...');

        $assets = Asset::where('company_id', $company->id)->get();

        if ($assets->isEmpty()) {
            $this->command->warn('No assets found.');
            return;
        }

        foreach (range(1, 25) as $index) {
            PredictiveMaintenance::factory()->create([
                'asset_id' => $assets->random()->id,
                'company_id' => $company->id,
            ]);
        }

        $this->command->info('Created 25 predictive maintenance records.');
    }
}

