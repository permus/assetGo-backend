<?php

namespace Database\Factories;

use App\Models\ImportSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportFile>
 */
class ImportFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_session_id' => ImportSession::factory(),
            'path' => 'imports/' . Str::uuid() . '.xlsx',
            'original_name' => fake()->word() . '_import.xlsx',
            'file_type' => fake()->randomElement(['xlsx', 'csv', 'xls']),
            'file_size' => fake()->numberBetween(1024, 5242880),
        ];
    }
}

