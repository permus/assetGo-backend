<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocationTemplate>
 */
class LocationTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $structures = [
            [
                'Building' => [
                    'Floor 1' => ['Room 101', 'Room 102', 'Room 103'],
                    'Floor 2' => ['Room 201', 'Room 202', 'Room 203'],
                ]
            ],
            [
                'Warehouse' => [
                    'Section A' => ['Aisle 1', 'Aisle 2'],
                    'Section B' => ['Aisle 3', 'Aisle 4'],
                ]
            ],
            [
                'Office' => [
                    'Main Floor' => ['Reception', 'Conference Room', 'Break Room'],
                    'Upper Floor' => ['Office 1', 'Office 2', 'Meeting Room'],
                ]
            ],
        ];

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true) . ' Template',
            'structure' => fake()->randomElement($structures),
        ];
    }
}

