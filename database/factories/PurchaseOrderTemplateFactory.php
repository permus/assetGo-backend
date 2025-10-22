<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrderTemplate>
 */
class PurchaseOrderTemplateFactory extends Factory
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
            'name' => fake()->words(3, true) . ' Template',
            'description' => fake()->sentence(),
            'template_data' => [
                'terms' => 'Net 30',
                'shipping_method' => fake()->randomElement(['Standard', 'Express', 'Overnight']),
                'default_items' => [],
            ],
            'is_active' => fake()->boolean(90),
            'created_by' => User::factory(),
        ];
    }
}

