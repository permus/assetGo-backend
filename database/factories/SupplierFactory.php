<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1, // Will be overridden in seeder
            'supplier_code' => 'SUP-' . strtoupper(fake()->bothify('????####')),
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'tax_registration_number' => fake()->optional(0.8)->numerify('TRN-########'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'alternate_phone' => fake()->optional(0.7)->phoneNumber(),
            'website' => fake()->optional(0.6)->url(),
            'street_address' => fake()->optional(0.9)->streetAddress(),
            'city' => fake()->optional(0.9)->city(),
            'state' => fake()->optional(0.9)->state(),
            'postal_code' => fake()->optional(0.9)->postcode(),
            'payment_terms' => fake()->optional(0.8)->randomElement([
                'Net 30 days',
                'Net 45 days',
                'Net 60 days',
                '2/10 Net 30',
                '1/15 Net 45'
            ]),
            'terms' => fake()->optional(0.7)->sentence(),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP', 'AED', 'SAR']),
            'credit_limit' => fake()->optional(0.6)->randomFloat(2, 1000, 100000),
            'delivery_lead_time' => fake()->optional(0.8)->numberBetween(1, 30),
            'notes' => fake()->optional(0.5)->paragraph(),
            'extra' => fake()->optional(0.3)->randomElement([
                ['category' => 'electronics', 'rating' => 'A+'],
                ['category' => 'office', 'rating' => 'A'],
                ['category' => 'it', 'rating' => 'B+']
            ]),
        ];
    }
}
