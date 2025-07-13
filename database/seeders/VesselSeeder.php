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
                'vessel_name' => 'EVER GIVEN',
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
                'vessel_name' => 'MSC OSCAR',
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
                'vessel_name' => 'MAERSK EDINBURGH',
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
                'vessel_name' => 'ONE STORK',
                'voyage_number' => 'OS-2025-008',
                'eta' => Carbon::now()->addDays(5),
                'actual_arrival' => null,
                'port' => 'Laem Chabang',
                'status' => 'scheduled',
                'imo_number' => '9811003',
                'agent' => 'Ocean Network Express',
                'notes' => 'Weekly service from Japan'
            ]
        ];

        foreach ($vessels as $vessel) {
            Vessel::create($vessel);
        }
    }
}
