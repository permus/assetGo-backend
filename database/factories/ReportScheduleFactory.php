<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rrules = [
            'FREQ=DAILY;INTERVAL=1',
            'FREQ=WEEKLY;BYDAY=MO',
            'FREQ=MONTHLY;BYMONTHDAY=1',
            'FREQ=MONTHLY;BYMONTHDAY=15',
        ];

        return [
            'company_id' => Company::factory(),
            'template_id' => ReportTemplate::factory(),
            'name' => fake()->words(3, true) . ' Schedule',
            'description' => fake()->sentence(),
            'rrule' => fake()->randomElement($rrules),
            'timezone' => fake()->timezone(),
            'delivery_email' => fake()->email(),
            'delivery_options' => [
                'format' => 'pdf',
                'include_charts' => true,
            ],
            'enabled' => fake()->boolean(70),
            'last_run_at' => fake()->optional()->dateTimeBetween('-1 month', 'now'),
            'next_run_at' => fake()->dateTimeBetween('now', '+1 month'),
        ];
    }
}

