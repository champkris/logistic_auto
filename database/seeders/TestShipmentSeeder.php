<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Shipment;
use App\Models\Vessel;
use App\Models\Customer;
use App\Models\DropdownSetting;
use Carbon\Carbon;

class TestShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 8 test shipments with different vessels that match actual terminal configurations
     * These vessels should return real ETA data when checked against their respective terminals
     */
    public function run(): void
    {
        // Ensure we have at least one customer
        $customer = Customer::first();
        if (!$customer) {
            $customer = Customer::create([
                'company' => 'Test Company Ltd.',
                'name' => 'Test Contact',
                'email' => 'test@example.com',
                'phone' => '0812345678',
                'address' => '123 Test Street, Bangkok'
            ]);
        }

        // Terminal configurations with actual vessels that should return ETAs
        $terminalVessels = [
            [
                'terminal_code' => 'C1C2',
                'terminal_name' => 'Hutchison Ports',
                'vessel_name' => 'WAN HAI 517',
                'voyage_code' => 'S095',
                'full_name' => 'WAN HAI 517 S095',
                'port' => 'C1'
            ],
            [
                'terminal_code' => 'B4',
                'terminal_name' => 'TIPS',
                'vessel_name' => 'SRI SUREE',
                'voyage_code' => '25080S',
                'full_name' => 'SRI SUREE V.25080S',
                'port' => 'B4'
            ],
            [
                'terminal_code' => 'B5C3',
                'terminal_name' => 'LCIT',
                'vessel_name' => 'SKY SUNSHINE',
                'voyage_code' => '2513S',
                'full_name' => 'SKY SUNSHINE V.2513S',
                'port' => 'B5'
            ],
            [
                'terminal_code' => 'B3',
                'terminal_name' => 'ESCO',
                'vessel_name' => 'CUL NANSHA',
                'voyage_code' => '2528S',
                'full_name' => 'CUL NANSHA V.2528S',
                'port' => 'B3'
            ],
            [
                'terminal_code' => 'A0B1',
                'terminal_name' => 'LCB1',
                'vessel_name' => 'MARSA PRIDE',
                'voyage_code' => '528S',
                'full_name' => 'MARSA PRIDE 528S',
                'port' => 'A0'
            ],
            [
                'terminal_code' => 'B2',
                'terminal_name' => 'ShipmentLink',
                'vessel_name' => 'EVER BASIS',
                'voyage_code' => '0813-068S',
                'full_name' => 'EVER BASIS 0813-068S',
                'port' => 'B2'
            ],
            [
                'terminal_code' => 'SIAM',
                'terminal_name' => 'Siam Commercial',
                'vessel_name' => 'SAMPLE VESSEL',
                'voyage_code' => '001S',
                'full_name' => 'SAMPLE VESSEL V.001S',
                'port' => 'SIAM'
            ],
            [
                'terminal_code' => 'KERRY',
                'terminal_name' => 'Kerry Logistics',
                'vessel_name' => 'BUXMELODY',
                'voyage_code' => '230N',
                'full_name' => 'BUXMELODY 230N',
                'port' => 'KERRY'
            ]
        ];

        // Shipping teams and CS references
        $shippingTeams = ['pui', 'frank', 'gus', 'mon', 'noon', 'toon', 'ing', 'jow'];
        $csReferences = ['MEW', 'MON', 'NOON', 'ING', 'JOW'];

        foreach ($terminalVessels as $index => $vesselData) {
            // Create or find vessel
            $vessel = Vessel::firstOrCreate(
                ['vessel_name' => $vesselData['vessel_name'], 'voyage_number' => $vesselData['voyage_code']],
                [
                    'name' => $vesselData['vessel_name'],
                    'full_vessel_name' => $vesselData['full_name'],
                    'port' => $vesselData['port'],
                    'status' => 'scheduled',
                    'eta' => Carbon::now()->addDays(rand(1, 7)),
                    'notes' => "Test vessel for {$vesselData['terminal_name']} terminal",
                    'metadata' => [
                        'terminal_code' => $vesselData['terminal_code'],
                        'terminal_name' => $vesselData['terminal_name']
                    ]
                ]
            );

            // Create test shipment
            $shipment = Shipment::create([
                'customer_id' => $customer->id,
                'vessel_id' => $vessel->id,

                // Identification numbers
                'hbl_number' => 'HBL' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'mbl_number' => 'MBL' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                'invoice_number' => 'INV-2025-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),

                // Dates
                'client_requested_delivery_date' => Carbon::now()->addDays(rand(10, 20)),
                'planned_delivery_date' => Carbon::now()->addDays(rand(8, 15)),

                // Quantities
                'quantity_number' => rand(10, 100),
                'quantity_unit' => 'CTN',
                'quantity_days' => rand(5, 15),
                'weight_kgm' => rand(1000, 5000),

                // Port and logistics info
                'port_terminal' => $vesselData['port'],
                'shipping_team' => $shippingTeams[$index % count($shippingTeams)],
                'cs_reference' => $csReferences[$index % count($csReferences)],
                'voyage' => $vesselData['voyage_code'],

                // Status fields - using the component's default values
                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress', // Default safe value
                'tracking_status' => $index < 4 ? 'on_track' : ($index < 6 ? 'delay' : null),

                // Pickup and delivery info
                'pickup_location' => $vesselData['terminal_name'] . ' Terminal',
                'joint_pickup' => $index % 2 == 0 ? 'Yes' : 'No',
                'vessel_loading_status' => $index < 3 ? 'loaded' : 'pending',

                // Additional cargo info
                'cargo_details' => [
                    'description' => 'Test cargo for ' . $vesselData['terminal_name'],
                    'weight_kg' => rand(1000, 5000),
                    'volume_cbm' => rand(10, 50)
                ],

                // Thai status field
                'thai_status' => 'ทดสอบ',

                // Notes
                'notes' => "Test shipment for {$vesselData['terminal_name']} terminal with vessel {$vesselData['vessel_name']} {$vesselData['voyage_code']}. This should return ETA when checked.",

                // ETA check dates (simulate some already checked)
                'last_eta_check_date' => $index < 4 ? Carbon::now()->subHours(rand(1, 24)) : null,
                'bot_received_eta_date' => $index < 4 ? Carbon::now()->addDays(rand(2, 7)) : null,
            ]);

            echo "✅ Created test shipment #{$shipment->id} for {$vesselData['terminal_name']} - {$vesselData['full_name']}\n";
        }

        echo "\n🎉 Successfully created 8 test shipments with vessels that should return ETAs!\n";
        echo "Each shipment uses a different port terminal with known vessel configurations.\n";
        echo "Run vessel ETA checks to verify they return actual ETA data.\n";
    }
}