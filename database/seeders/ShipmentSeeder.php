<?php

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\Document;
use App\Models\Customer;
use App\Models\Vessel;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing vessels and customers
        $vessels = Vessel::all();
        $customers = Customer::all();

        // Get specific vessel IDs to match original data patterns
        $customerId1 = $customers->count() > 0 ? $customers->first()->id : 1;

        // Map vessels by name for correct assignments
        $vesselMap = [];
        foreach ($vessels as $vessel) {
            $vesselMap[$vessel->vessel_name] = $vessel->id;
        }

        // Use specific vessel IDs or fallback to first vessel
        $defaultVesselId = $vessels->count() > 0 ? $vessels->first()->id : null;

        $shipments = [
            [
                // Basic identification
                'hbl_number' => 'HBL000001',
                'mbl_number' => 'MBL000001',
                'invoice_number' => 'INV-2025-0001',

                // Quantity fields (matching current patterns)
                'quantity_number' => 91.00,
                'quantity_unit' => 'CTN',
                'quantity_days' => 15,
                'weight_kgm' => 2876.00,

                // Relationships - use default vessel for voyage 066N (WAN HAI 517 not in VesselSeeder)
                'vessel_id' => $defaultVesselId,
                'customer_id' => $customerId1,
                'voyage' => '066N',

                // Port and team assignments
                'port_terminal' => 'C1C2',
                'shipping_team' => 'tub',
                'cs_reference' => 'bow',
                'pickup_location' => 'SEAGREEN รับแล้ว',
                'joint_pickup' => 'Yes',

                // Status fields
                'customs_clearance_status' => 'received',
                'overtime_status' => 'ot1',
                'do_status' => 'received',
                'status' => 'in-progress',
                'vessel_loading_status' => 'loaded',
                'thai_status' => 'ทดสอบ',
                'tracking_status' => 'on_track',

                // Dates (datetime format)
                'client_requested_delivery_date' => Carbon::now()->addDays(7),
                'planned_delivery_date' => Carbon::now()->addDays(6),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => Carbon::now(),

                // Additional info
                'notes' => 'Test shipment for Hutchison Ports terminal with vessel WAN HAI 517 S095. This should return ETA when checked.',
                'cargo_details' => [
                    'weight_kg' => 2501,
                    'volume_cbm' => 17,
                    'description' => 'Test cargo for Hutchison Ports'
                ],
            ],
            [
                'hbl_number' => 'HBL000002',
                'mbl_number' => 'MBL000002',
                'invoice_number' => 'INV-2025-0002',

                'quantity_number' => 70.00,
                'quantity_unit' => 'CTN',
                'quantity_days' => 10,
                'weight_kgm' => 2437.00,

                'vessel_id' => $defaultVesselId,
                'customer_id' => $customerId1,
                'voyage' => '069N',

                'port_terminal' => 'B4',
                'shipping_team' => 'frank',
                'cs_reference' => 'MON',
                'pickup_location' => 'SEAGREEN รับแล้ว',
                'joint_pickup' => 'No',

                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'loaded',
                'thai_status' => 'ทดสอบ',
                'tracking_status' => 'on_track',

                'client_requested_delivery_date' => Carbon::now()->subDays(4),
                'planned_delivery_date' => Carbon::now()->subDays(4),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => Carbon::now()->subDays(4),

                'notes' => 'Test shipment for TIPS terminal with vessel SRI SUREE 25080S. This should return ETA when checked.',
                'cargo_details' => [
                    'weight_kg' => 3143,
                    'volume_cbm' => 16,
                    'description' => 'Test cargo for TIPS'
                ],
            ],
            [
                'hbl_number' => 'HBL000003',
                'mbl_number' => 'MBL000003',
                'invoice_number' => 'INV-2025-0003',

                'quantity_number' => 83.00,
                'quantity_unit' => 'CTN',
                'quantity_days' => 9,
                'weight_kgm' => 4073.00,

                'vessel_id' => $vesselMap['SKY SUNSHINE'] ?? $defaultVesselId,
                'customer_id' => $customerId1,
                'voyage' => '2513S',

                'port_terminal' => 'B5C3',
                'shipping_team' => 'gus',
                'cs_reference' => 'NOON',
                'pickup_location' => 'SEAGREEN รับแล้ว',
                'joint_pickup' => 'Yes',

                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'loaded',
                'thai_status' => 'ทดสอบ',
                'tracking_status' => 'on_track',

                'client_requested_delivery_date' => Carbon::now(),
                'planned_delivery_date' => Carbon::now()->addDays(14),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => Carbon::now()->addDays(14),

                'notes' => 'Test shipment for LCIT terminal with vessel SKY SUNSHINE 2513S. This should return ETA when checked.',
                'cargo_details' => [
                    'weight_kg' => 3592,
                    'volume_cbm' => 45,
                    'description' => 'Test cargo for LCIT'
                ],
            ],
            [
                'hbl_number' => 'HBL000004',
                'mbl_number' => 'MBL000004',
                'invoice_number' => 'INV-2025-0004',

                'quantity_number' => 12.00,
                'quantity_unit' => 'CTN',
                'quantity_days' => 7,
                'weight_kgm' => 4281.00,

                'vessel_id' => $defaultVesselId,
                'customer_id' => $customerId1,
                'voyage' => '2518S',

                'port_terminal' => 'B3',
                'shipping_team' => 'mon',
                'cs_reference' => 'ING',
                'pickup_location' => 'SEAGREEN รับแล้ว',
                'joint_pickup' => 'No',

                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'pending',
                'thai_status' => 'ทดสอบ',
                'tracking_status' => 'on_track',

                'client_requested_delivery_date' => Carbon::now()->addDays(21),
                'planned_delivery_date' => Carbon::now()->subDays(1),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => Carbon::now()->subDays(1),

                'notes' => 'Test shipment for ESCO terminal with vessel CUL NANSHA 2528S. This should return ETA when checked.',
                'cargo_details' => [
                    'weight_kg' => 1398,
                    'volume_cbm' => 36,
                    'description' => 'Test cargo for ESCO'
                ],
            ],
            [
                'hbl_number' => 'HBL000005',
                'mbl_number' => 'MBL000005',
                'invoice_number' => 'INV-2025-0005',

                'quantity_number' => 52.00,
                'quantity_unit' => 'CTN',
                'quantity_days' => 5,
                'weight_kgm' => 3406.00,

                'vessel_id' => $defaultVesselId,
                'customer_id' => $customerId1,
                'voyage' => '2511S',

                'port_terminal' => 'A0B1',
                'shipping_team' => 'noon',
                'cs_reference' => 'JOW',
                'pickup_location' => 'SEAGREEN รับแล้ว',
                'joint_pickup' => 'Yes',

                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'pending',
                'thai_status' => 'ทดสอบ',
                'tracking_status' => 'on_track',

                'client_requested_delivery_date' => Carbon::now()->addDays(11),
                'planned_delivery_date' => Carbon::now()->addDays(12),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => Carbon::now()->addDays(12),

                'notes' => 'Test shipment for LCB1 terminal with vessel MARSA PRIDE 528S. This should return ETA when checked.',
                'cargo_details' => [
                    'weight_kg' => 3227,
                    'volume_cbm' => 47,
                    'description' => 'Test cargo for LCB1'
                ],
            ],
            [
                'hbl_number' => 'HBL000006',
                'mbl_number' => 'MBL000006',
                'invoice_number' => 'INV-2025-0006',

                'quantity_number' => 13.00,
                'quantity_unit' => 'CTN',
                'quantity_days' => 14,
                'weight_kgm' => 2776.00,

                'vessel_id' => $vesselMap['EVER BASIS'] ?? $defaultVesselId,
                'customer_id' => $customerId1,
                'voyage' => '0813-068S',

                'port_terminal' => 'B2',
                'shipping_team' => 'toon',
                'cs_reference' => 'MEW',
                'pickup_location' => 'SEAGREEN รับแล้ว',
                'joint_pickup' => 'No',

                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'pending',
                'thai_status' => 'ทดสอบ',
                'tracking_status' => 'on_track',

                'client_requested_delivery_date' => Carbon::now()->addDays(13),
                'planned_delivery_date' => Carbon::now()->subDays(6),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => Carbon::now()->subDays(6),

                'notes' => 'Test shipment for ShipmentLink terminal with vessel EVER BASIS 0813-068S. This should return ETA when checked.',
                'cargo_details' => [
                    'weight_kg' => 1094,
                    'volume_cbm' => 39,
                    'description' => 'Test cargo for ShipmentLink'
                ],
            ],
            [
                'hbl_number' => 'HBL000007',
                'mbl_number' => 'MBL000007',
                'invoice_number' => 'INV-2025-0007',

                'quantity_number' => 55.00,
                'quantity_unit' => 'CTN',
                'quantity_days' => 13,
                'weight_kgm' => 3164.00,

                'vessel_id' => $vesselMap['SAMPLE VESSEL'] ?? $defaultVesselId,
                'customer_id' => $customerId1,
                'voyage' => '001S',

                'port_terminal' => 'SIAM',
                'shipping_team' => 'ing',
                'cs_reference' => 'MON',
                'pickup_location' => 'SEAGREEN รับแล้ว',
                'joint_pickup' => 'Yes',

                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'pending',
                'thai_status' => 'ทดสอบ',
                'tracking_status' => 'not_found',

                'client_requested_delivery_date' => Carbon::now()->addDays(16),
                'planned_delivery_date' => Carbon::now()->addDays(15),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => null,

                'notes' => 'Test shipment for Siam Commercial terminal with vessel SAMPLE VESSEL 001S. This should return ETA when checked.',
                'cargo_details' => [
                    'weight_kg' => 3254,
                    'volume_cbm' => 10,
                    'description' => 'Test cargo for Siam Commercial'
                ],
            ],
            [
                'hbl_number' => 'HBL000008',
                'mbl_number' => 'MBL000008',
                'invoice_number' => 'INV-2025-0008',

                'quantity_number' => 73.00,
                'quantity_unit' => 'CTN',
                'quantity_days' => 7,
                'weight_kgm' => 1510.00,

                'vessel_id' => $vesselMap['BUXMELODY'] ?? $defaultVesselId,
                'customer_id' => $customerId1,
                'voyage' => '230N',

                'port_terminal' => 'KERRY',
                'shipping_team' => 'jow',
                'cs_reference' => 'NOON',
                'pickup_location' => 'SEAGREEN รับแล้ว',
                'joint_pickup' => 'No',

                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'pending',
                'thai_status' => 'ทดสอบ',
                'tracking_status' => 'not_found',

                'client_requested_delivery_date' => Carbon::now()->addDays(14),
                'planned_delivery_date' => Carbon::now()->addDays(10),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => null,

                'notes' => 'Test shipment for Kerry Logistics terminal with vessel BUXMELODY 230N. This should return ETA when checked.',
                'cargo_details' => [
                    'weight_kg' => 3854,
                    'volume_cbm' => 33,
                    'description' => 'Test cargo for Kerry Logistics'
                ],
            ]
        ];

        foreach ($shipments as $shipmentData) {
            $shipment = Shipment::create($shipmentData);
            
            // Create sample documents for each shipment
            $this->createDocumentsForShipment($shipment);
        }
    }

    private function createDocumentsForShipment($shipment)
    {
        $documents = [
            [
                'type' => 'do',
                'document_name' => 'Delivery Order',
                'status' => 'pending',
                'cost' => 1500.00,
                'due_date' => Carbon::now()->addDay(),
                'issued_by' => 'Port Authority'
            ],
            [
                'type' => 'customs_declaration',
                'document_name' => 'Customs Declaration Form',
                'status' => 'received',
                'received_date' => Carbon::now()->subDays(2),
                'issued_by' => 'CS Shipping BKK'
            ],
            [
                'type' => 'bill_of_lading',
                'document_name' => 'Original Bill of Lading',
                'status' => 'approved',
                'received_date' => Carbon::now()->subDays(3),
                'issued_by' => 'Shipping Line'
            ]
        ];

        foreach ($documents as $docData) {
            $docData['shipment_id'] = $shipment->id;
            Document::create($docData);
        }
    }
}
