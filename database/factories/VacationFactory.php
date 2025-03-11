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
        // Using 2025 to match the year used in BoardSeeder
        $currentYear = 2025;
        
        return [
            'schoolyear' => $currentYear,
            'vacation_dates' => json_encode([
                // Winter break (end of previous year to beginning of current)
                fake()->dateTimeBetween("$currentYear-01-01", "$currentYear-01-10")->format('Y-m-d'),
                // Spring break
                fake()->dateTimeBetween("$currentYear-02-15", "$currentYear-03-01")->format('Y-m-d'),
                // Easter break
                fake()->dateTimeBetween("$currentYear-04-01", "$currentYear-04-15")->format('Y-m-d'),
                // May holiday
                fake()->dateTimeBetween("$currentYear-05-01", "$currentYear-05-10")->format('Y-m-d'),
                // Summer break
                fake()->dateTimeBetween("$currentYear-07-15", "$currentYear-08-31")->format('Y-m-d'),
                // Fall break
                fake()->dateTimeBetween("$currentYear-10-15", "$currentYear-10-30")->format('Y-m-d'),
                // Winter break (end of current year)
                fake()->dateTimeBetween("$currentYear-12-20", "$currentYear-12-31")->format('Y-m-d'),
            ]),
        ];
    }
}
