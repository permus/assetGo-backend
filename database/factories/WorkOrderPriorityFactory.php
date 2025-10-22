<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderPriority>
 */
class WorkOrderPriorityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $priorities = ['Low', 'Normal', 'High', 'Critical', 'Urgent', 'Emergency'];
        $name = fake()->randomElement($priorities);

        return [
            'company_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'is_management' => fake()->boolean(30),
            'sort' => fake()->numberBetween(1, 100),
        ];
    }
}

