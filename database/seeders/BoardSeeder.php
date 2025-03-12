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
            
            // Calculate sprint dates based on board duration
            $boardStartTimestamp = strtotime($startDate);
            $boardEndTimestamp = strtotime($endDate);
            $totalDuration = $boardEndTimestamp - $boardStartTimestamp;
            $sprintDuration = $totalDuration / 2;
            
            // Create sprints using a loop
            $sprints = [];
            $statuses = ['active', 'inactive', 'locked', 'checked'];
            
            for ($j = 1; $j <= 4; $j++) {
                $sprint = new stdClass();
                $sprint->title = 'Sprint ' . $j;
                $sprint->id = $j;
                $sprint->status = $statuses[$j - 1];
                
                if ($j === 1) {
                    $sprint->start_date = $startDate;
                    $sprint->end_date = date('Y-m-d', $boardStartTimestamp + $sprintDuration);
                } else {
                    $sprint->start_date = date('Y-m-d', strtotime($sprints[0]->end_date . ' +1 day'));
                    $sprint->end_date = $endDate;
                }
                
                $sprints[] = $sprint;
            }

            $user = User::factory()->create();
            
            // Generate non-working days within the board's date range
            $nonWorkingDays = [];
            $currentDate = strtotime($startDate);
            $endTimestamp = strtotime($endDate);
            
            // Generate 3-5 random non-working days within the board's timeframe
            $numNonWorkingDays = rand(3, 5);
            while (count($nonWorkingDays) < $numNonWorkingDays && $currentDate < $endTimestamp) {
                $randomDayOffset = rand(0, floor(($endTimestamp - $currentDate) / 86400));
                $randomDay = date('Y-m-d', $currentDate + ($randomDayOffset * 86400));
                
                // Avoid duplicates
                if (!in_array($randomDay, $nonWorkingDays)) {
                    $nonWorkingDays[] = $randomDay;
                }
            }
            
            $board = Board::factory()->create([
                'creator_id' => $user['id'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'sprints' => json_encode($sprints),
                'non_working_days' => json_encode($nonWorkingDays),
            ]);
            $board->users()->attach($user['id']);
        }
    }
}
