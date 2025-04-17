<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use stdClass;

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
        $sprint1 = new stdClass();
        $sprint1->title = 'Sprint 1';
        $sprint1->start_date = $this->faker->date;
        $sprint1->end_date = $this->faker->date;
        $sprint1->status = $this->faker->randomElement(['done', 'not_done', 'active']);
        $sprint1->id = 1;

        $sprint2 = new stdClass();
        $sprint2->title = 'Sprint 2';
        $sprint2->start_date = $this->faker->date;
        $sprint2->end_date = $this->faker->date;
        $sprint2->status = $this->faker->randomElement(['done', 'not_done', 'active']);
        $sprint2->id = 2;

        return [
            'title' => $this->faker->sentence,
            'description' => $this->faker->sentence,
            'start_date' => $this->faker->date,
            'end_date' => $this->faker->date,
            'sprints' => json_encode([$sprint1, $sprint2]),
            'non_working_days' => json_encode($this->faker->randomElements(['2023-01-01', '2023-01-02', '2023-01-03'])),
            'weekdays' => json_encode($this->faker->randomElements(['monday', 'tuesday', 'wednesday'])),
            'status' => $this->faker->randomElement(['active', 'archived', 'completed'])
        ];
    }
}
