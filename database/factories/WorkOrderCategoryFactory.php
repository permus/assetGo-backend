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
        
        $name = fake()->randomElement($categories);

        return [
            'company_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'sort' => fake()->numberBetween(1, 100),
        ];
    }
}

