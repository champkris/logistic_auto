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

        // Ensure we have at least one vessel and customer
        $vesselId1 = $vessels->count() > 0 ? $vessels->first()->id : null;
        $vesselId2 = $vessels->count() > 1 ? $vessels->skip(1)->first()->id : null;
        $customerId1 = $customers->count() > 0 ? $customers->first()->id : 1;
        $customerId2 = $customers->count() > 1 ? $customers->skip(1)->first()->id : 2;

        $shipments = [
            [
                // Basic identification
                'hbl_number' => 'HBL-2025-001',
                'mbl_number' => 'MBL-2025-001',
                'invoice_number' => 'INV-2025-001',

                // Quantity fields (new)
                'quantity_number' => 2.0,
                'quantity_unit' => '40_hc',
                'quantity_days' => 5,
                'weight_kgm' => 25000.50,

                // Relationships (vessel_id now nullable)
                'vessel_id' => $vesselId1,
                'customer_id' => $customerId1,
                'voyage' => 'V.2025-001S',

                // Port and team assignments
                'port_terminal' => 'C1C2',
                'shipping_team' => 'tub',
                'cs_reference' => 'bow',
                'pickup_location' => 'warehouse_a',
                'joint_pickup' => false,

                // Status fields
                'customs_clearance_status' => 'pending',
                'overtime_status' => 'none',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'scheduled',
                'thai_status' => 'อยู่ระหว่างดำเนินการ',
                'tracking_status' => 'on_track',

                // Dates (datetime format)
                'client_requested_delivery_date' => Carbon::now()->addDays(7),
                'planned_delivery_date' => Carbon::now()->addDays(6),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => null,

                // Additional info
                'notes' => 'Fragile electronics - handle with care',
                'cargo_details' => [
                    'containers' => ['MSKU123456', 'MSKU123457'],
                    'commodity' => 'Electronics',
                    'value' => 'USD 50,000'
                ],
            ],
            [
                'hbl_number' => 'HBL-2025-002',
                'mbl_number' => 'MBL-2025-002',
                'invoice_number' => 'INV-2025-002',

                'quantity_number' => 1.0,
                'quantity_unit' => '20_dry',
                'quantity_days' => 3,
                'weight_kgm' => 15000.00,

                'vessel_id' => $vesselId2,
                'customer_id' => $customerId2,
                'voyage' => 'V.2025-002S',

                'port_terminal' => 'B4',
                'shipping_team' => 'gus',
                'cs_reference' => 'meena',
                'pickup_location' => 'port_office',
                'joint_pickup' => true,

                'customs_clearance_status' => 'received',
                'overtime_status' => 'ot1',
                'do_status' => 'received',
                'status' => 'completed',
                'vessel_loading_status' => 'loaded',
                'thai_status' => 'เสร็จสิ้น',
                'tracking_status' => 'on_track',

                'client_requested_delivery_date' => Carbon::now()->subDays(2),
                'planned_delivery_date' => Carbon::now()->subDays(3),
                'actual_delivery_date' => Carbon::now()->subDays(2),
                'last_eta_check_date' => Carbon::now()->subDays(5),
                'bot_received_eta_date' => Carbon::now()->subDays(4),

                'notes' => 'Completed shipment delivered on time',
                'cargo_details' => [
                    'containers' => ['MSCU789012'],
                    'commodity' => 'Textiles',
                    'weight' => '12.3 tons',
                    'value' => 'USD 35,000'
                ],
            ],
            [
                // Testing new terminals and nullable vessel_id
                'hbl_number' => 'HBL-2025-003',
                'mbl_number' => 'MBL-2025-003',
                'invoice_number' => null,

                'quantity_number' => 3.0,
                'quantity_unit' => '40_rf',
                'quantity_days' => null,
                'weight_kgm' => 30000.00,

                'vessel_id' => null, // Testing nullable vessel_id
                'customer_id' => $customerId1,
                'voyage' => null,

                'port_terminal' => 'B5C3',
                'shipping_team' => 'beer',
                'cs_reference' => 'mew',
                'pickup_location' => 'customer_location',
                'joint_pickup' => false,

                'customs_clearance_status' => 'processing',
                'overtime_status' => 'none',
                'do_status' => 'processing',
                'status' => 'in-progress',
                'vessel_loading_status' => 'pending',
                'thai_status' => 'รอดำเนินการ',
                'tracking_status' => 'delay',

                'client_requested_delivery_date' => Carbon::now()->addDays(14),
                'planned_delivery_date' => Carbon::now()->addDays(10),
                'actual_delivery_date' => null,
                'last_eta_check_date' => null,
                'bot_received_eta_date' => null,

                'notes' => 'Refrigerated container for frozen goods',
                'cargo_details' => [
                    'type' => 'Frozen Food',
                    'temperature' => '-18°C',
                    'special_requirements' => 'Maintain cold chain'
                ],
            ],
            [
                // Testing Siam Commercial terminal
                'hbl_number' => 'HBL-2025-004',
                'mbl_number' => 'MBL-2025-004',
                'invoice_number' => 'INV-2025-004',

                'quantity_number' => 100.0,
                'quantity_unit' => 'lcl',
                'quantity_days' => 2,
                'weight_kgm' => 5000.00,

                'vessel_id' => $vesselId1,
                'customer_id' => $customerId2,
                'voyage' => 'V.001S',

                'port_terminal' => 'SIAM', // New terminal
                'shipping_team' => 'frank',
                'cs_reference' => 'tem',
                'pickup_location' => 'warehouse_b',
                'joint_pickup' => true,

                'customs_clearance_status' => 'pending',
                'overtime_status' => 'ot2',
                'do_status' => 'pending',
                'status' => 'in-progress',
                'vessel_loading_status' => 'loading',
                'thai_status' => 'กำลังโหลดสินค้า',
                'tracking_status' => 'on_track',

                'client_requested_delivery_date' => Carbon::now()->addDays(5),
                'planned_delivery_date' => Carbon::now()->addDays(4),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now()->subHours(6),
                'bot_received_eta_date' => Carbon::now()->subHours(3),

                'notes' => 'LCL shipment from Siam Commercial terminal',
                'cargo_details' => [
                    'type' => 'Mixed Goods',
                    'cbm' => 15.5
                ],
            ],
            [
                // Testing Kerry Logistics terminal
                'hbl_number' => 'HBL-2025-005',
                'mbl_number' => 'MBL-2025-005',
                'invoice_number' => 'INV-2025-005',

                'quantity_number' => 1.0,
                'quantity_unit' => 'iso_tank',
                'quantity_days' => 7,
                'weight_kgm' => 24000.00,

                'vessel_id' => $vesselId2,
                'customer_id' => $customerId1,
                'voyage' => '230N',

                'port_terminal' => 'KERRY', // New terminal
                'shipping_team' => 'mon',
                'cs_reference' => 'kartoon',
                'pickup_location' => 'terminal_gate',
                'joint_pickup' => false,

                'customs_clearance_status' => 'received',
                'overtime_status' => 'none',
                'do_status' => 'received',
                'status' => 'in-progress',
                'vessel_loading_status' => 'scheduled',
                'thai_status' => 'กำลังดำเนินการ',
                'tracking_status' => 'on_track',

                'client_requested_delivery_date' => Carbon::now()->addDays(8),
                'planned_delivery_date' => Carbon::now()->addDays(7),
                'actual_delivery_date' => null,
                'last_eta_check_date' => Carbon::now(),
                'bot_received_eta_date' => null,

                'notes' => 'ISO tank for chemical transport via Kerry Logistics',
                'cargo_details' => [
                    'type' => 'Chemicals',
                    'un_number' => 'N/A',
                    'safety_requirements' => 'Standard handling procedures'
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
