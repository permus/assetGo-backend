<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'owner_id' => fake()->uuid(),
            'subscription_status' => 'trial',
            'subscription_expires_at' => fake()->dateTimeBetween('now', '+30 days'),
            'business_type' => fake()->randomElement(['LLC', 'Corporation', 'Partnership', 'Sole Proprietorship']),
            'industry' => fake()->randomElement(['Technology', 'Manufacturing', 'Healthcare', 'Finance', 'Retail']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'logo' => null,
        ];
    }
}
