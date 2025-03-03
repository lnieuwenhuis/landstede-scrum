<?php

namespace Database\Seeders;

use App\Models\Group;
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
        $groups = Group::all();

        foreach ($groups as $group) {
            $users = User::factory()->count(4)->create();

            foreach ($users as $user) {
                $user->groups()->attach($group->id);
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
