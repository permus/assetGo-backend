<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Admin::count() > 0) {
            $this->command->info('Admins already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding admin...');

        Admin::create([
            'name' => 'Admin',
            'email' => 'admin@assetgo.com',
            'password' => Hash::make('admin123'),
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin created successfully.');
        $this->command->info('Email: admin@assetgo.com');
        $this->command->info('Password: admin123');
    }
}
