<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\vacation>
 */
class VacationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schoolyear' => fake()->year(),
            'vacation_dates' => json_encode([
                fake()->dateTimeBetween('2023-10-01', '2023-10-31')->format('Y-m-d'),
                fake()->dateTimeBetween('2023-12-20', '2024-01-07')->format('Y-m-d'),
                fake()->dateTimeBetween('2024-02-15', '2024-02-28')->format('Y-m-d'),
                fake()->dateTimeBetween('2024-04-27', '2024-05-12')->format('Y-m-d'),
                fake()->dateTimeBetween('2024-07-01', '2024-08-31')->format('Y-m-d'),
            ]),
        ];
    }
}
