<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $boardCount = 3;
        for ($i = 0; $i < $boardCount; $i++) {
            $user = User::factory()->create();
            $board = Board::factory()->create([
                'creator_id' => $user['id'],
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+1 week')),
            ]);
            $board->users()->attach($user['id']);
        }
    }
}
