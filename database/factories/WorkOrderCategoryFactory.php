<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderCategory>
 */
class WorkOrderCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Preventive Maintenance', 'Corrective Maintenance', 'Emergency Repair',
            'Inspection', 'Calibration', 'Installation', 'Upgrade', 'Electrical',
            'Mechanical', 'HVAC', 'Plumbing', 'Safety'
        ];
        
        $name = fake()->unique()->randomElement($categories);

        return [
            'company_id' => null,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'sort' => fake()->numberBetween(1, 100),
        ];
    }
}

