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
    }
}
