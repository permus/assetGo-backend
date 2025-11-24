<?php

namespace Database\Seeders;

use App\Models\ReportRun;
use App\Models\Company;
use App\Models\User;
use App\Models\ReportTemplate;
use Illuminate\Database\Seeder;

class ReportRunSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (ReportRun::where('company_id', $company->id)->count() >= 10) {
            $this->command->info('Report runs already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding report runs...');

        $users = User::where('company_id', $company->id)->get();
        $templates = ReportTemplate::where('company_id', $company->id)->get();

        if ($users->isEmpty() || $templates->isEmpty()) {
            $this->command->warn('No users or templates found. Creating basic data...');
            return;
        }

        foreach (range(1, 12) as $index) {
            ReportRun::factory()->create([
                'company_id' => $company->id,
                'user_id' => $users->random()->id,
                'template_id' => $templates->random()->id,
            ]);
        }

        $this->command->info('Created 12 report runs.');
    }
}

