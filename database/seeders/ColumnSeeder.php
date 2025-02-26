<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Seeder;

class ColumnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $boards = Board::all();

        foreach ($boards as $board) {
            \App\Models\Column::factory()->count(4)->create(['board_id' => $board->id]);
            \App\Models\Column::factory()->create(['board_id' => $board->id, 'is_done_column' => true, 'title' => 'Done']);
        }
    }
}
