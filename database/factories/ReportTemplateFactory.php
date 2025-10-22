<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportTemplate>
 */
class ReportTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reportKeys = [
            'assets.summary', 'assets.detailed', 'assets.by_location', 'assets.by_category',
            'maintenance.scheduled', 'maintenance.completed', 'maintenance.overdue',
            'inventory.stock_levels', 'inventory.transactions', 'inventory.alerts',
            'financial.depreciation', 'financial.purchase_orders'
        ];

        return [
            'company_id' => Company::factory(),
            'owner_id' => User::factory(),
            'name' => fake()->words(3, true) . ' Report',
            'description' => fake()->sentence(),
            'report_key' => fake()->randomElement($reportKeys),
            'definition' => [
                'columns' => ['id', 'name', 'status', 'created_at'],
                'aggregates' => ['count', 'sum'],
            ],
            'default_filters' => [
                'status' => 'active',
                'date_range' => '30_days',
            ],
            'is_shared' => fake()->boolean(50),
            'is_public' => fake()->boolean(20),
        ];
    }
}

