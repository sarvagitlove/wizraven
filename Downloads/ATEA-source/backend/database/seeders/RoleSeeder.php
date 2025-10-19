<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Role::create([
            'role_name' => 'admin',
            'description' => 'Administrator with full access to manage members and system settings'
        ]);

        \App\Models\Role::create([
            'role_name' => 'user',
            'description' => 'Regular member with access to profile management and member features'
        ]);
    }
}
