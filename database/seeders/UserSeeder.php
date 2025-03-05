<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $boards = Board::all();

        foreach ($boards as $board) {
            $users = User::factory()->count(4)->create();

            foreach ($users as $user) {
                $user->boards()->attach($board->id);
            }
        }

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@admin.admin',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }
}
