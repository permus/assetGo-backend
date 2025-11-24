<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        if (Company::count() > 0) {
            $this->command->info('Companies already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding companies...');

        $owner = User::firstOrCreate(
            ['email' => 'owner@assetgo.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Owner',
                'user_type' => 'admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create main demo company
        $company = Company::create([
            'name' => 'AssetGo Demo Company',
            'slug' => 'assetgo-demo',
            'owner_id' => $owner->id,
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addYear(),
            'business_type' => 'Technology',
            'industry' => 'IT Services',
            'phone' => '+1-555-0100',
            'email' => 'contact@assetgo-demo.com',
            'address' => '123 Business Street, Suite 100, New York, NY 10001',
            'currency' => 'USD',
            'settings' => [
                'timezone' => 'America/New_York',
                'date_format' => 'Y-m-d',
            ],
        ]);

        $owner->update(['company_id' => $company->id]);
        $this->command->info("Created company: {$company->name}");

        // Create additional companies for testing
        $industries = ['Manufacturing', 'Healthcare', 'Education', 'Retail', 'Construction', 'Transportation', 'Finance', 'Hospitality'];
        $businessTypes = ['Enterprise', 'SMB', 'Startup', 'Non-Profit'];
        
        foreach (range(1, 9) as $index) {
            $companyOwner = User::create([
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'email' => 'owner' . $index . '@company' . $index . '.com',
                'user_type' => 'admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            $newCompany = Company::create([
                'name' => fake()->company(),
                'slug' => fake()->unique()->slug(),
                'owner_id' => $companyOwner->id,
                'subscription_status' => fake()->randomElement(['active', 'trial', 'suspended']),
                'subscription_expires_at' => now()->addMonths(fake()->numberBetween(1, 12)),
                'business_type' => fake()->randomElement($businessTypes),
                'industry' => fake()->randomElement($industries),
                'phone' => fake()->phoneNumber(),
                'email' => fake()->companyEmail(),
                'address' => fake()->address(),
                'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
                'settings' => [
                    'timezone' => fake()->timezone(),
                    'date_format' => 'Y-m-d',
                ],
            ]);

            $companyOwner->update(['company_id' => $newCompany->id]);
        }

        $this->command->info('Created 10 companies in total.');
    }
}
