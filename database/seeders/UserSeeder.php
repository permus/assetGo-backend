<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (User::where('company_id', $company->id)->count() > 1) {
            $this->command->info('Users already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding users...');

        $users = [
            ['first_name' => 'Admin', 'last_name' => 'User', 'email' => 'admin@assetgo.com', 'user_type' => 'admin', 'hourly_rate' => 50.00],
            ['first_name' => 'Manager', 'last_name' => 'Smith', 'email' => 'manager@assetgo.com', 'user_type' => 'manager', 'hourly_rate' => 40.00],
            ['first_name' => 'John', 'last_name' => 'Technician', 'email' => 'tech1@assetgo.com', 'user_type' => 'user', 'hourly_rate' => 30.00],
            ['first_name' => 'Sarah', 'last_name' => 'Engineer', 'email' => 'tech2@assetgo.com', 'user_type' => 'user', 'hourly_rate' => 35.00],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                    'email_verified_at' => now(),
                ])
            );
        }

        $this->command->info('Created ' . count($users) . ' users.');
    }
}
