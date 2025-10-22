<?php

namespace Database\Seeders;

use App\Models\AIRecommendation;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AIRecommendationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (AIRecommendation::where('company_id', $company->id)->count() >= 20) {
            $this->command->info('AI recommendations already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding AI recommendations...');

        foreach (range(1, 25) as $index) {
            AIRecommendation::factory()->create([
                'company_id' => $company->id,
            ]);
        }

        $this->command->info('Created 25 AI recommendations.');
    }
}

