<?php

namespace Database\Seeders;

use App\Models\Column;
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
        $startDate = Carbon::create(2023, 1, 1);
        $endDate = Carbon::create(2023, 1, 31);

        foreach ($columns as $column) {
            $user = $column->board->users()->inRandomOrder()->first();
        
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