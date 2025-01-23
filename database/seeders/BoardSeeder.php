<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $boards = [
            ['title' => 'Bord1', 'description' => 'aslkdfhalkjfdsh'],
            ['title' => 'Bord2', 'description' => 'lasdjflkjsahgl'],
        ];

        foreach ($boards as $board) {
            Board::create($board);
        }
    }
}
