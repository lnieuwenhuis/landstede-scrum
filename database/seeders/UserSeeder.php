<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            $users = User::factory()->count(5)->create();

            foreach ($users as $user) {
                $user->groups()->attach($group->id);
            }
        }
    }
}
