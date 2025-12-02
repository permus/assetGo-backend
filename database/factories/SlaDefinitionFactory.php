<?php

namespace Database\Factories;

use App\Models\SlaDefinition;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkOrderCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlaDefinition>
 */
class SlaDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(3, true) . ' SLA',
            'description' => fake()->optional(0.7)->paragraph(2),
            'applies_to' => fake()->randomElement(['work_orders', 'maintenance', 'both']),
            'priority_level' => fake()->optional(0.6)->randomElement(['low', 'medium', 'high', 'critical', 'ppm']),
            'category_id' => WorkOrderCategory::factory()->create()?->id,
            'response_time_hours' => fake()->randomFloat(2, 0.5, 24),
            'containment_time_hours' => fake()->optional(0.5)->randomFloat(2, 0.5, 48),
            'completion_time_hours' => fake()->randomFloat(2, 1, 72),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the SLA is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the SLA is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the SLA applies to work orders.
     */
    public function forWorkOrders(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => 'work_orders',
        ]);
    }

    /**
     * Indicate that the SLA applies to maintenance.
     */
    public function forMaintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => 'maintenance',
        ]);
    }

    /**
     * Indicate that the SLA applies to both.
     */
    public function forBoth(): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => 'both',
        ]);
    }

    /**
     * Set a specific category for the SLA.
     */
    public function withCategory(WorkOrderCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    /**
     * Set a specific priority level for the SLA.
     */
    public function withPriority(string $priorityLevel): static
    {
        return $this->state(fn (array $attributes) => [
            'priority_level' => $priorityLevel,
        ]);
    }
}

