<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groupCount = 3;
        for ($i = 0; $i < $groupCount; $i++) {
            $user = User::factory()->create();
            $group = Group::factory()->create(['creator_id' => $user['id']]);
            $group->users()->attach($user);
        }
    }
}
