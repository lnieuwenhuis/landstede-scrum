<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Group;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            Board::factory()->create(['group_id' => $group->id, 'start_date' => '2023-01-01', 'end_date' => '2023-01-31']);
        }
    }
}
