<?php

namespace Database\Seeders;

use App\Models\ImportSession;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class ImportSessionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (ImportSession::where('company_id', $company->id)->count() >= 10) {
            $this->command->info('Import sessions already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding import sessions...');

        $users = User::where('company_id', $company->id)->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found.');
            return;
        }

        foreach (range(1, 12) as $index) {
            ImportSession::factory()->create([
                'company_id' => $company->id,
                'user_id' => $users->random()->id,
            ]);
        }

        $this->command->info('Created 12 import sessions.');
    }
}

