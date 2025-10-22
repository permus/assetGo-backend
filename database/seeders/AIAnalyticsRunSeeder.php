<?php

namespace Database\Seeders;

use App\Models\AIAnalyticsRun;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AIAnalyticsRunSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (AIAnalyticsRun::where('company_id', $company->id)->count() >= 20) {
            $this->command->info('AI analytics runs already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding AI analytics runs...');

        foreach (range(1, 25) as $index) {
            AIAnalyticsRun::factory()->create([
                'company_id' => $company->id,
            ]);
        }

        $this->command->info('Created 25 AI analytics runs.');
    }
}

