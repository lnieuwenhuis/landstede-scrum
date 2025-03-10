<?php

namespace Database\Seeders;

use App\Models\Column;
use App\Models\Board;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $columns = Column::all();

        foreach ($columns as $column) {
            // Get the board associated with this column
            $board = $column->board;
            
            // Use the board's start and end dates for the card status updates
            $startDate = Carbon::parse($board->start_date);
            $endDate = Carbon::parse($board->end_date);
            
            $user = $board->users()->inRandomOrder()->first();
        
            \App\Models\Card::factory()->count(5)->create([
                'column_id' => $column->id,
                'user_id' => $user->id, 
                'status_updated_at' => Carbon::createFromTimestamp(
                    rand($startDate->timestamp, $endDate->timestamp)
                )
            ]);
        }
    }
}