<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Updated to reflect latest database structure with:
     * - Nullable vessel_id in shipments
     * - New quantity fields (quantity_number, quantity_unit, quantity_days)
     * - New terminals (SIAM, KERRY)
     * - Updated datetime fields
     * - Email fields in dropdown settings
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding with latest schema...');

        // Base users and auth
        $this->call([
            EasternAirUserSeeder::class,  // Eastern Air specific users
            UserSeeder::class,             // Additional test users
        ]);

        // Dropdown settings (must be before other data that references them)
        $this->call([
            DropdownSettingsSeeder::class, // Updated with SIAM and KERRY terminals
        ]);

        // Core business entities
        $this->call([
            CustomerSeeder::class,         // Customer records
            VesselSeeder::class,          // Vessel records including new terminal vessels
            ShipmentSeeder::class,        // Updated shipment records with new fields
        ]);

        // Optional: Sample data for testing (disabled - needs updating)
        // if (app()->environment('local', 'development')) {
        //     $this->command->info('Loading sample data for development environment...');
        //     $this->call([
        //         SampleDataSeeder::class,
        //     ]);
        // }

        $this->command->info('Database seeding completed successfully!');
        $this->command->info('- 8 terminals configured (including SIAM and KERRY)');
        $this->command->info('- Vessel tracking integration ready');
        $this->command->info('- Shipments with nullable vessel_id support');
        $this->command->info('- New quantity fields (number, unit, days) configured');
    }
}
