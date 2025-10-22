<?php

namespace Database\Factories;

use App\Models\ImportSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportMapping>
 */
class ImportMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_session_id' => ImportSession::factory(),
            'mappings' => [
                'name' => 'Asset Name',
                'serial_number' => 'Serial Number',
                'category' => 'Category',
                'location' => 'Location',
                'purchase_date' => 'Purchase Date',
                'purchase_price' => 'Purchase Price',
            ],
            'user_overrides' => [
                'default_status' => 'active',
                'default_department' => 'IT',
            ],
        ];
    }
}

