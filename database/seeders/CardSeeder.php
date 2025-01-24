<?php

namespace Database\Seeders;

use App\Models\Column;
use App\Models\User;
use Illuminate\Database\Seeder;

class CardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $columns = Column::all();

        foreach ($columns as $column) {
            $user = $column->board->group->users()->inRandomOrder()->first();
        
            \App\Models\Card::factory()->count(5)->create(['column_id' => $column->id,'user_id'=> $user->id]);
        }
    }
}
