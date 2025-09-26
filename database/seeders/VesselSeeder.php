<?php

namespace Database\Seeders;

use App\Models\Vessel;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class VesselSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vessels = [
            [
                'name' => 'EVER GIVEN',
                'vessel_name' => 'EVER GIVEN',
                'full_vessel_name' => 'EVER GIVEN',
                'voyage_number' => 'EG-2025-001',
                'eta' => Carbon::now()->addDays(2),
                'actual_arrival' => null,
                'port' => 'Laem Chabang',
                'status' => 'scheduled',
                'imo_number' => '9811000',
                'agent' => 'Bangkok Shipping Agency',
                'notes' => 'Container vessel from Singapore'
            ],
            [
                'name' => 'MSC OSCAR',
                'vessel_name' => 'MSC OSCAR',
                'full_vessel_name' => 'MSC OSCAR',
                'voyage_number' => 'MSC-2025-015',
                'eta' => Carbon::now()->addDays(1),
                'actual_arrival' => null,
                'port' => 'Laem Chabang',
                'status' => 'scheduled',
                'imo_number' => '9811001',
                'agent' => 'MSC Thailand',
                'notes' => 'Large container vessel from Europe'
            ],
            [
                'name' => 'MAERSK EDINBURGH',
                'vessel_name' => 'MAERSK EDINBURGH',
                'full_vessel_name' => 'MAERSK EDINBURGH',
                'voyage_number' => 'ME-2025-022',
                'eta' => Carbon::yesterday(),
                'actual_arrival' => Carbon::now()->subHours(6),
                'port' => 'Laem Chabang',
                'status' => 'arrived',
                'imo_number' => '9811002',
                'agent' => 'Maersk Thailand',
                'notes' => 'Arrived early, containers ready for discharge'
            ],
            [
                'name' => 'ONE STORK',
                'vessel_name' => 'ONE STORK',
                'full_vessel_name' => 'ONE STORK',
                'voyage_number' => 'OS-2025-008',
                'eta' => Carbon::now()->addDays(5),
                'actual_arrival' => null,
                'port' => 'Laem Chabang',
                'status' => 'scheduled',
                'imo_number' => '9811003',
                'agent' => 'Ocean Network Express',
                'notes' => 'Weekly service from Japan'
            ],
            // Terminal-specific test vessels from VesselTrackingService
            [
                'name' => 'SKY SUNSHINE',
                'vessel_name' => 'SKY SUNSHINE',
                'full_vessel_name' => 'SKY SUNSHINE',
                'voyage_number' => 'V.2513S',
                'eta' => Carbon::now()->addDays(3),
                'actual_arrival' => null,
                'port' => 'LCIT Terminal B5C3',
                'status' => 'scheduled',
                'imo_number' => '9811004',
                'agent' => 'LCIT Shipping',
                'notes' => 'LCIT terminal integration test vessel'
            ],
            [
                'name' => 'EVER BASIS',
                'vessel_name' => 'EVER BASIS',
                'full_vessel_name' => 'EVER BASIS',
                'voyage_number' => '0813-068S',
                'eta' => Carbon::parse('2025-09-22 00:00:00'),
                'actual_arrival' => null,
                'port' => 'ShipmentLink Terminal B2',
                'status' => 'scheduled',
                'imo_number' => '9811005',
                'agent' => 'Evergreen Marine',
                'notes' => 'ShipmentLink terminal integration - ETA extracted from live schedule'
            ],
            [
                'name' => 'BUXMELODY',
                'vessel_name' => 'BUXMELODY',
                'full_vessel_name' => 'BUXMELODY',
                'voyage_number' => '230N',
                'eta' => Carbon::now()->addDays(4),
                'actual_arrival' => null,
                'port' => 'Kerry Logistics',
                'status' => 'scheduled',
                'imo_number' => '9811006',
                'agent' => 'Kerry Logistics',
                'notes' => 'Kerry Logistics terminal integration test vessel'
            ],
            [
                'name' => 'SAMPLE VESSEL',
                'vessel_name' => 'SAMPLE VESSEL',
                'full_vessel_name' => 'SAMPLE VESSEL',
                'voyage_number' => 'V.001S',
                'eta' => Carbon::now()->addDays(6),
                'actual_arrival' => null,
                'port' => 'Siam Commercial',
                'status' => 'scheduled',
                'imo_number' => '9811007',
                'agent' => 'Siam Commercial Port',
                'notes' => 'Siam Commercial terminal - n8n LINE integration test vessel'
            ]
        ];

        foreach ($vessels as $vessel) {
            Vessel::create($vessel);
        }
    }
}
