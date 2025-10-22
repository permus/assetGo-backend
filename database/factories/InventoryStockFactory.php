<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InventoryPart;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $onHand = fake()->numberBetween(0, 500);
        $reserved = fake()->numberBetween(0, min(50, $onHand));
        
        return [
            'company_id' => Company::factory(),
            'part_id' => InventoryPart::factory(),
            'location_id' => Location::factory(),
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => $onHand - $reserved,
            'average_cost' => fake()->randomFloat(2, 5, 500),
            'last_counted_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'last_counted_by' => User::factory(),
            'bin_location' => fake()->bothify('BIN-?##-?##'),
        ];
    }
}

