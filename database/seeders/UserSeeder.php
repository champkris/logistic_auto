<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ================================
        // CS SHIPPING LCB USERS (Original)
        // ================================
        
        // Create admin user for CS Shipping LCB
        User::create([
            'name' => 'CS Admin',
            'email' => 'admin@csshipping.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // Create test user for development
        User::create([
            'name' => 'Test User',
            'email' => 'test@csshipping.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // Create manager user
        User::create([
            'name' => 'LCB Manager',
            'email' => 'manager@csshipping.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // Create operator user
        User::create([
            'name' => 'LCB Operator',
            'email' => 'operator@csshipping.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // ================================
        // TEST USERS (Additional test accounts)
        // ================================
        // Eastern Air users are now created in EasternAirUserSeeder

        // Test shipping team member
        User::create([
            'name' => 'Test Shipper',
            'email' => 'shipper@test.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // Test CS team member
        User::create([
            'name' => 'Test CS Rep',
            'email' => 'cs@test.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // Test warehouse staff
        User::create([
            'name' => 'Test Warehouse',
            'email' => 'warehouse@test.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // ================================
        // DEVELOPMENT USERS (For Testing)
        // ================================
        
        // Create additional test users for development
        User::factory(3)->create();
    }
}
