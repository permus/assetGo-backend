<?php

namespace Database\Seeders;

use App\Models\AIAnalyticsHistory;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class AIAnalyticsHistorySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (AIAnalyticsHistory::where('company_id', $company->id)->count() >= 20) {
            $this->command->info('AI analytics history already exists. Skipping.');
            return;
        }

        $this->command->info('Seeding AI analytics history...');

        $users = User::where('company_id', $company->id)->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found.');
            return;
        }

        foreach (range(1, 25) as $index) {
            AIAnalyticsHistory::factory()->create([
                'user_id' => $users->random()->id,
                'company_id' => $company->id,
            ]);
        }

        $this->command->info('Created 25 AI analytics history records.');
    }
}

