<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Board>
 */
class BoardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'description' => $this->faker->sentence,
            'start_date' => $this->faker->date,
            'end_date' => $this->faker->date,
            'sprints' => json_encode([
                ['name' => 'Sprint 1', 'start_date' => $this->faker->date, 'end_date' => $this->faker->date],
                ['name' => 'Sprint 2', 'start_date' => $this->faker->date, 'end_date' => $this->faker->date],
            ]),
            'non_working_days' => json_encode($this->faker->randomElements(['2023-01-01', '2023-01-02', '2023-01-03'])),
            'status' => $this->faker->randomElement(['active', 'archived', 'completed'])
        ];
    }
}
