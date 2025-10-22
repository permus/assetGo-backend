<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportRun>
 */
class ReportRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 month', 'now');
        $executionTimeMs = fake()->numberBetween(100, 5000);
        $completedAt = (clone $startedAt)->modify("+{$executionTimeMs} milliseconds");
        $status = fake()->randomElement(['queued', 'running', 'success', 'failed']);

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'template_id' => ReportTemplate::factory(),
            'report_key' => 'assets.summary',
            'params' => ['company_id' => 1],
            'filters' => ['status' => 'active'],
            'format' => fake()->randomElement(['pdf', 'excel', 'csv']),
            'status' => $status,
            'row_count' => $status === 'success' ? fake()->numberBetween(0, 1000) : null,
            'file_path' => $status === 'success' ? 'reports/' . fake()->uuid() . '.pdf' : null,
            'error_message' => $status === 'failed' ? fake()->sentence() : null,
            'started_at' => $startedAt,
            'completed_at' => in_array($status, ['success', 'failed']) ? $completedAt : null,
            'execution_time_ms' => in_array($status, ['success', 'failed']) ? $executionTimeMs : null,
        ];
    }
}

