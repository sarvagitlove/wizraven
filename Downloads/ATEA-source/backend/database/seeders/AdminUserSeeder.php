<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin role
        $adminRole = Role::where('role_name', 'admin')->first();
        
        if (!$adminRole) {
            $this->command->error('Admin role not found. Please run RoleSeeder first.');
            return;
        }

        // Create admin user if doesn't exist
        $adminUser = User::where('email', 'admin@atea.org')->first();
        
        if (!$adminUser) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@atea.org',
                'password' => Hash::make('admin123'), // Default password
                'google_id' => 'admin-google-id-123', // Matches frontend mock
                'avatar' => null,
                'role_id' => $adminRole->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $this->command->info('Admin user created successfully.');
            $this->command->info('Email: admin@atea.org');
            $this->command->info('Password: admin123');
        } else {
            // Update existing admin user with Google ID for mock authentication
            $adminUser->update([
                'google_id' => 'admin-google-id-123',
                'status' => 'active',
                'role_id' => $adminRole->id
            ]);
            
            $this->command->info('Admin user updated successfully.');
        }
    }
}