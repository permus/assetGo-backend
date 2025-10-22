<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();
        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Run CompanySeeder first.');
            return;
        }

        $this->command->info('Seeding team members for all companies...');

        // Create team member data templates
        $firstNames = [
            'James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph',
            'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan', 'Jessica',
            'Thomas', 'Christopher', 'Daniel', 'Matthew', 'Anthony', 'Mark', 'Donald', 'Steven',
            'Sarah', 'Karen', 'Nancy', 'Lisa', 'Betty', 'Margaret', 'Sandra', 'Ashley',
            'Paul', 'Andrew', 'Joshua', 'Kenneth', 'Kevin', 'Brian', 'George', 'Edward',
            'Emily', 'Donna', 'Michelle', 'Dorothy', 'Carol', 'Amanda', 'Melissa', 'Deborah',
            'Ronald', 'Timothy', 'Jason', 'Jeffrey', 'Charles', 'Larry', 'Jose', 'Frank'
        ];

        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
            'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas',
            'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White',
            'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker', 'Young',
            'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
            'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell',
            'Carter', 'Roberts', 'Gomez', 'Phillips', 'Evans', 'Turner', 'Diaz', 'Parker'
        ];

        $specializations = [
            'Technician', 'Engineer', 'Specialist', 'Analyst', 'Coordinator',
            'Supervisor', 'Inspector', 'Mechanic', 'Electrician', 'Operator'
        ];

        $totalCreated = 0;
        $totalCompanies = 0;

        foreach ($companies as $company) {
            $existingTeamCount = User::where('company_id', $company->id)
                ->where('user_type', 'team')
                ->count();

            if ($existingTeamCount >= 50) {
                $this->command->info("Company '{$company->name}' already has 50+ team members. Skipping.");
                continue;
            }

            $roles = Role::all();
            if ($roles->isEmpty()) {
                $this->command->warn("No roles found for company '{$company->name}'. Skipping.");
                continue;
            }

            // Create 50 team members per company
            $companyCreatedCount = 0;
            $targetCount = 50 - $existingTeamCount;

            for ($i = 1; $i <= $targetCount; $i++) {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $specialization = $specializations[array_rand($specializations)];
                
                // Generate unique email with company slug
                $emailBase = strtolower($firstName . '.' . $lastName . '.' . $company->id . '.' . $i);
                $email = $emailBase . '@team.assetgo.com';

                // Check if email already exists
                if (User::where('email', $email)->exists()) {
                    // Try with additional random suffix
                    $email = $emailBase . '.' . rand(1000, 9999) . '@team.assetgo.com';
                    if (User::where('email', $email)->exists()) {
                        continue;
                    }
                }

                $teamMember = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'user_type' => 'team',
                    'company_id' => $company->id,
                    'hourly_rate' => fake()->randomFloat(2, 25, 75),
                    'email_verified_at' => fake()->boolean(80) ? now() : null,
                    'created_by' => $company->owner_id,
                ]);

                // Assign a random role
                $role = $roles->random();
                $teamMember->roles()->attach($role->id);

                $companyCreatedCount++;
            }

            if ($companyCreatedCount > 0) {
                $totalTeamMembers = User::where('company_id', $company->id)
                    ->where('user_type', 'team')
                    ->count();
                $this->command->info("Company '{$company->name}': Created {$companyCreatedCount} team members. Total: {$totalTeamMembers}");
                $totalCreated += $companyCreatedCount;
                $totalCompanies++;
            }
        }

        $this->command->info("========================================");
        $this->command->info("Team seeding completed!");
        $this->command->info("Total companies processed: {$totalCompanies}");
        $this->command->info("Total team members created: {$totalCreated}");
        $this->command->info("========================================");
    }
}

