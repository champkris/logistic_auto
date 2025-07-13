<?php

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\Document;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ShipmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shipments = [
            [
                'shipment_number' => 'LCB-2025-001',
                'consignee' => 'บริษัท ใจดี อิมปอร์ต จำกัด',
                'hbl_number' => 'HBL001-2025',
                'mbl_number' => 'MBL001-2025',
                'invoice_number' => 'INV-2025-001',
                'vessel_id' => 1, // EVER GIVEN
                'customer_id' => 1,
                'port_of_discharge' => 'Laem Chabang',
                'status' => 'planning',
                'planned_delivery_date' => Carbon::now()->addDays(4),
                'total_cost' => 25000.00,
                'cargo_details' => [
                    'containers' => ['MSKU123456'],
                    'commodity' => 'Electronics',
                    'weight' => '15.5 tons',
                    'value' => 'USD 50,000'
                ],
                'notes' => 'Fragile goods - handle with care'
            ],
            [
                'shipment_number' => 'LCB-2025-002',
                'consignee' => 'บริษัท รุ่งเรือง เทรดดิ้ง จำกัด',
                'hbl_number' => 'HBL002-2025',
                'mbl_number' => 'MBL002-2025',
                'invoice_number' => 'INV-2025-002',
                'vessel_id' => 2, // MSC OSCAR
                'customer_id' => 2,
                'port_of_discharge' => 'Laem Chabang',
                'status' => 'documents_preparation',
                'planned_delivery_date' => Carbon::now()->addDays(3),
                'total_cost' => 18500.00,
                'cargo_details' => [
                    'containers' => ['MSCU789012'],
                    'commodity' => 'Textiles',
                    'weight' => '12.3 tons',
                    'value' => 'USD 35,000'
                ],
                'notes' => 'Customer prefers morning delivery'
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
