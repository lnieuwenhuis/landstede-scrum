<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $groups = [
            ['title' => 'Admins', 'description' => 'Admin users'],
            ['title' => 'Users', 'description' => 'Regular users'],
        ];

        foreach ($groups as $group) {
            \App\Models\Group::create($group);
        }
    }
}
