<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Column;
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
            $column = Column::factory()->create([
                'title' => 'Project Backlog',
                'is_done_column' => false,
                'board_id' => $board->id,
                'status' => 'active',
                'user_created' => false
            ]);
            $column->save();
    
            $column = Column::factory()->create([
                'title' => 'Sprint Backlog',
                'is_done_column' => false,
                'board_id' => $board->id,
                'status'=> 'active',
                'user_created' => false
            ]);
            $column->save();
    
            $column = Column::factory()->create([
                'title' => 'Done',
                'is_done_column' => true,
                'board_id' => $board->id,
                'status'=> 'active',
                'user_created' => false
            ]);
            $column->save();
        }
    }
}
