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
            ['first_name' => 'Michael', 'last_name' => 'Johnson', 'email' => 'mjohnson@assetgo.com', 'user_type' => 'user', 'hourly_rate' => 32.00],
            ['first_name' => 'Emily', 'last_name' => 'Davis', 'email' => 'edavis@assetgo.com', 'user_type' => 'user', 'hourly_rate' => 33.00],
            ['first_name' => 'David', 'last_name' => 'Wilson', 'email' => 'dwilson@assetgo.com', 'user_type' => 'manager', 'hourly_rate' => 42.00],
            ['first_name' => 'Jennifer', 'last_name' => 'Brown', 'email' => 'jbrown@assetgo.com', 'user_type' => 'user', 'hourly_rate' => 31.00],
            ['first_name' => 'Robert', 'last_name' => 'Taylor', 'email' => 'rtaylor@assetgo.com', 'user_type' => 'user', 'hourly_rate' => 34.00],
            ['first_name' => 'Lisa', 'last_name' => 'Anderson', 'email' => 'landerson@assetgo.com', 'user_type' => 'user', 'hourly_rate' => 36.00],
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

        // Create additional random users
        $userTypes = ['user', 'manager', 'admin'];
        foreach (range(1, 15) as $index) {
            User::firstOrCreate(
                ['email' => 'user' . $index . '@assetgo.com'],
                [
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'user_type' => fake()->randomElement($userTypes),
                    'hourly_rate' => fake()->randomFloat(2, 25, 60),
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                    'email_verified_at' => now(),
                ]
            );
        }

        $this->command->info('Created ' . User::where('company_id', $company->id)->count() . ' users.');
    }
}
