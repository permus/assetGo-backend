<?php

namespace Database\Seeders;

use App\Models\AIRecognitionHistory;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class AIRecognitionHistorySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (AIRecognitionHistory::where('company_id', $company->id)->count() >= 10) {
            $this->command->info('AI recognition history already exists. Skipping.');
            return;
        }

        $this->command->info('Seeding AI recognition history...');

        $users = User::where('company_id', $company->id)->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found.');
            return;
        }

        foreach (range(1, 12) as $index) {
            $confidenceScore = fake()->randomFloat(2, 70, 99);
            AIRecognitionHistory::create([
                'user_id' => $users->random()->id,
                'company_id' => $company->id,
                'image_paths' => [
                    'assets/images/' . fake()->uuid() . '.jpg',
                    'assets/images/' . fake()->uuid() . '.jpg',
                ],
                'confidence_score' => $confidenceScore,
                'recognition_result' => [
                    'confidence' => $confidenceScore,
                    'detected_objects' => fake()->randomElements(['laptop', 'monitor', 'keyboard', 'mouse', 'printer'], rand(2, 4)),
                    'suggestions' => [fake()->sentence(), fake()->sentence()],
                ],
                'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
                'updated_at' => fake()->dateTimeBetween('-6 months', 'now'),
            ]);
        }

        $this->command->info('Created 12 AI recognition history records.');
    }
}

