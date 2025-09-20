<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@easternair.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin123456'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Create manager user
        User::updateOrCreate(
            ['email' => 'manager@easternair.com'],
            [
                'name' => 'Manager',
                'password' => Hash::make('manager123456'),
                'role' => User::ROLE_MANAGER,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Create regular user
        User::updateOrCreate(
            ['email' => 'user@easternair.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('user123456'),
                'role' => User::ROLE_USER,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Default users created:');
        $this->command->info('Admin: admin@easternair.com / admin123456');
        $this->command->info('Manager: manager@easternair.com / manager123456');
        $this->command->info('User: user@easternair.com / user123456');
    }
}