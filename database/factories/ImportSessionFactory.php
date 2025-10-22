<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportSession>
 */
class ImportSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed', 'cancelled']),
            'original_name' => fake()->word() . '_assets.xlsx',
            'stored_name' => Str::uuid() . '.xlsx',
            'file_type' => fake()->randomElement(['xlsx', 'csv', 'xls']),
            'file_size' => fake()->numberBetween(1024, 5242880),
            'uploaded_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'meta' => [
                'total_rows' => fake()->numberBetween(10, 500),
                'processed_rows' => fake()->numberBetween(0, 500),
                'errors' => fake()->numberBetween(0, 10),
            ],
        ];
    }
}

