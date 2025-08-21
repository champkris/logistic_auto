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
        // EASTERN AIR USERS (Production)
        // ================================

        // IMPORT TEAM - SEA LCB
        User::create([
            'name' => 'SEA LCB Import 1',
            'email' => 'sealcb_import1@easternair.co.th',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'SEA LCB Import 2',
            'email' => 'sealcb_import2@easternair.co.th',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'SEA LCB Import 3',
            'email' => 'sealcb_import3@easternair.co.th',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'SEA LCB Import 4',
            'email' => 'sealcb_import4@easternair.co.th',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // STAFF MEMBERS
        User::create([
            'name' => 'Nattapon Bun',
            'email' => 'nattapon.bun@easternair.co.th',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // EXPORT TEAM
        User::create([
            'name' => 'Export LCB',
            'email' => 'export_lcb@easternair.co.th',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Phonnapha Ro',
            'email' => 'phonnapha.ro@easternair.co.th',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        // ADMIN USERS
        User::create([
            'name' => 'Peachy (Admin)',
            'email' => 'peachy@easternair.co.th',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'IT Department (Admin)',
            'email' => 'it@easternair.co.th',
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
