<?php

namespace Database\Seeders;

use App\Models\AITrainingData;
use App\Models\Company;
use App\Models\Asset;
use Illuminate\Database\Seeder;

class AITrainingDataSeeder extends Seeder
{
    public function run(): void
    {
        if (AITrainingData::count() >= 20) {
            $this->command->info('AI training data already exists. Skipping.');
            return;
        }

        $this->command->info('Seeding AI training data...');

        // Get recognition history records to link to
        $recognitions = \App\Models\AIRecognitionHistory::all();

        if ($recognitions->isEmpty()) {
            $this->command->warn('No AI recognition history found. Run AIRecognitionHistorySeeder first.');
            return;
        }

        $fieldNames = ['asset_name', 'category', 'location', 'condition', 'serial_number'];
        $correctionTypes = ['text', 'classification', 'confidence'];

        foreach (range(1, 25) as $index) {
            AITrainingData::create([
                'recognition_id' => $recognitions->random()->id,
                'field_name' => fake()->randomElement($fieldNames),
                'original_value' => fake()->optional()->words(3, true),
                'corrected_value' => fake()->words(3, true),
                'correction_type' => fake()->randomElement($correctionTypes),
                'user_notes' => fake()->optional()->sentence(),
                'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
            ]);
        }

        $this->command->info('Created 25 AI training data records.');
    }
}

