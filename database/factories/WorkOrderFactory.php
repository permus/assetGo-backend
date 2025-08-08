<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['open', 'in_progress', 'completed', 'on_hold', 'cancelled'];
        $priorities = ['low', 'medium', 'high', 'critical'];
        
        $status = $this->faker->randomElement($statuses);
        $priority = $this->faker->randomElement($priorities);
        
        $dueDate = $this->faker->optional(0.7)->dateTimeBetween('now', '+30 days');
        $completedAt = null;
        
        // If status is completed, set completed_at
        if ($status === 'completed') {
            $completedAt = $this->faker->dateTimeBetween('-30 days', 'now');
        }

        return [
            'title' => $this->faker->sentence(3, 6),
            'description' => $this->faker->optional(0.8)->paragraph(2, 4),
            'priority' => $priority,
            'status' => $status,
            'due_date' => $dueDate,
            'completed_at' => $completedAt,
            'asset_id' => Asset::inRandomOrder()->first()?->id,
            'location_id' => Location::inRandomOrder()->first()?->id,
            'assigned_to' => User::inRandomOrder()->first()?->id,
            'assigned_by' => User::inRandomOrder()->first()?->id,
            'created_by' => User::inRandomOrder()->first()?->id,
            'company_id' => Company::inRandomOrder()->first()?->id,
            'estimated_hours' => $this->faker->optional(0.6)->randomFloat(2, 0.5, 40),
            'actual_hours' => $status === 'completed' ? $this->faker->optional(0.7)->randomFloat(2, 0.5, 40) : null,
            'notes' => $this->faker->optional(0.4)->paragraph(1, 2),
            'meta' => $this->faker->optional(0.3)->randomElements([
                'requires_special_tools' => true,
                'safety_equipment_required' => true,
                'customer_approval_needed' => true,
                'parts_ordered' => true,
            ], $this->faker->numberBetween(0, 2), false),
        ];
    }

    /**
     * Indicate that the work order is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the work order is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the work order is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the work order is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'status' => $this->faker->randomElement(['open', 'in_progress']),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the work order is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Indicate that the work order is critical priority.
     */
    public function criticalPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'critical',
        ]);
    }
}
