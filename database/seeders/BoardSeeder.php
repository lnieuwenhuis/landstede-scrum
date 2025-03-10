<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Seeder;
use stdClass;
use Faker\Factory as Faker;

class BoardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $boardCount = 3;
        $currentYear = 2025;
        $faker = Faker::create();
        
        for ($i = 0; $i < $boardCount; $i++) {
            $startMonth = rand(1, 10); 
            $startDate = date('Y-m-d', strtotime("$currentYear-$startMonth-01"));
            $endDate = date('Y-m-d', strtotime("$startDate +2 months -1 day"));
            
            $boardStartTimestamp = strtotime($startDate);
            $boardEndTimestamp = strtotime($endDate);
            $totalDuration = $boardEndTimestamp - $boardStartTimestamp;
            $sprintDuration = $totalDuration / 2;
            
            $sprint1 = new stdClass();
            $sprint1->title = 'Sprint 1';
            $sprint1->start_date = $startDate;
            $sprint1->end_date = date('Y-m-d', $boardStartTimestamp + $sprintDuration);
            $sprint1->status = $faker->randomElement(['done', 'not_done', 'active']);
            $sprint1->id = 1;

            $sprint2 = new stdClass();
            $sprint2->title = 'Sprint 2';
            $sprint2->start_date = date('Y-m-d', strtotime($sprint1->end_date . ' +1 day'));
            $sprint2->end_date = $endDate;
            $sprint2->status = $faker->randomElement(['done', 'not_done', 'active']);
            $sprint2->id = 2;

            $user = User::factory()->create();
            $board = Board::factory()->create([
                'creator_id' => $user['id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'sprints' => json_encode([$sprint1, $sprint2]),
            ]);
            $board->users()->attach($user['id']);
        }
    }
}
