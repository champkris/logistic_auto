<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Vessel;
use App\Models\Shipment;

class SampleDataSeeder extends Seeder
{
    public function run()
    {
        // Create sample customers
        $customers = [
            [
                'name' => 'John Smith',
                'company' => 'ABC Import Co., Ltd.',
                'email' => 'john@abcimport.com',
                'phone' => '+66 2 123 4567',
                'address' => '123 Sukhumvit Road, Watthana, Bangkok 10110',
                'is_active' => true,
                'notification_preferences' => [
                    'email_updates' => true,
                    'sms_notifications' => true,
                    'daily_reports' => true,
                ],
            ],
            [
                'name' => 'Sarah Wilson',
                'company' => 'Global Trading Ltd.',
                'email' => 'sarah@globaltrading.co.th',
                'phone' => '+66 2 987 6543',
                'address' => '456 Silom Road, Bang Rak, Bangkok 10500',
                'is_active' => true,
                'notification_preferences' => [
                    'email_updates' => true,
                    'sms_notifications' => false,
                    'daily_reports' => true,
                ],
            ],
            [
                'name' => 'Michael Chen',
                'company' => 'Asia Pacific Logistics',
                'email' => 'michael@aplogistics.com',
                'phone' => '+66 33 456 7890',
                'address' => '789 Industrial Estate, Laem Chabang, Chonburi 20230',
                'is_active' => true,
                'notification_preferences' => [
                    'email_updates' => true,
                    'sms_notifications' => true,
                    'daily_reports' => false,
                ],
            ],
            [
                'name' => 'Lisa Johnson',
                'company' => 'Textile Imports Thailand',
                'email' => 'lisa@textileimports.co.th',
                'phone' => '+66 2 555 1234',
                'address' => '321 Ratchada Road, Huai Khwang, Bangkok 10310',
                'is_active' => true,
                'notification_preferences' => [
                    'email_updates' => true,
                    'sms_notifications' => false,
                    'daily_reports' => true,
                ],
            ],
            [
                'name' => 'David Kim',
                'company' => 'Electronics Export Co.',
                'email' => 'david@electronics-export.com',
                'phone' => '+66 2 777 8888',
                'address' => '654 Petchburi Road, Ratchathewi, Bangkok 10400',
                'is_active' => false,
                'notification_preferences' => [
                    'email_updates' => false,
                    'sms_notifications' => false,
                    'daily_reports' => false,
                ],
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::create($customerData);
        }

        // Create sample vessels
        $vessels = [
            [
                'vessel_name' => 'EVER BUILD',
                'name' => 'EVER BUILD',
                'imo_number' => 'IMO1234567',
                'status' => 'scheduled',
                'eta' => '2025-10-01 14:00:00',
                'port' => 'Laem Chabang',
            ],
            [
                'vessel_name' => 'MARSA PRIDE',
                'name' => 'MARSA PRIDE',
                'imo_number' => 'IMO2345678',
                'status' => 'arrived',
                'eta' => '2025-07-21 21:00:00',
                'port' => 'Bangkok Port',
            ],
            [
                'vessel_name' => 'WAN HAI 517',
                'name' => 'WAN HAI 517',
                'imo_number' => 'IMO3456789',
                'status' => 'arrived',
                'eta' => '2025-07-30 08:00:00',
                'port' => 'Laem Chabang',
            ],
            [
                'vessel_name' => 'SRI SUREE',
                'name' => 'SRI SUREE',
                'imo_number' => 'IMO4567890',
                'status' => 'scheduled',
                'eta' => '2025-08-05 16:00:00',
                'port' => 'Map Ta Phut',
            ],
        ];

        foreach ($vessels as $vesselData) {
            Vessel::create($vesselData);
        }

        // Create sample shipments
        $customers = Customer::all();
        $vessels = Vessel::all();

        $shipments = [
            [
                'shipment_number' => 'CSL20250001',
                'consignee' => 'ABC Import Co., Ltd.',
                'hbl_number' => 'HBL2025001',
                'mbl_number' => 'MBL2025001',
                'invoice_number' => 'INV2025001',
                'quantity_days' => 5,
                'do_pickup_date' => '2025-10-02 10:00:00',
                'weight_kgm' => 1500.5,
                'fcl_type' => '20 COILS',
                'container_arrival' => 'DHDA A V.2506',
                'customer_id' => $customers->first()->id,
                'vessel_id' => $vessels->where('vessel_name', 'EVER BUILD')->first()->id,
                'port_of_discharge' => 'Laem Chabang',
                'berth_location' => 'A0',
                'joint_pickup' => 'PUI',
                'customs_entry' => 'NOON',
                'vessel_loading_status' => 'MON',
                'status' => 'documents_preparation',
                'thai_status' => 'กำลังเตรียมเอกสาร',
                'planned_delivery_date' => '2025-10-03',
                'total_cost' => 25000.00,
                'notes' => 'High priority delivery - customer VIP',
                'cargo_details' => [
                    'description' => 'Electronic Components',
                    'weight_kg' => 1500.5,
                    'volume_cbm' => 12.3,
                ],
            ],
            [
                'shipment_number' => 'CSL20250002',
                'consignee' => 'Global Trading Ltd.',
                'hbl_number' => 'HBL2025002',
                'mbl_number' => 'MBL2025002',
                'invoice_number' => 'INV2025002',
                'quantity_days' => 3,
                'do_pickup_date' => '2025-07-23 14:00:00',
                'weight_kgm' => 800.0,
                'fcl_type' => '10 Jun',
                'container_arrival' => 'DHDA B V.2506',
                'customer_id' => $customers->skip(1)->first()->id,
                'vessel_id' => $vessels->where('vessel_name', 'MARSA PRIDE')->first()->id,
                'port_of_discharge' => 'Bangkok Port',
                'berth_location' => 'C3',
                'joint_pickup' => 'FRANK',
                'customs_entry' => 'BOW',
                'vessel_loading_status' => 'ING',
                'status' => 'ready_for_delivery',
                'thai_status' => 'พร้อมส่งมอบ',
                'planned_delivery_date' => '2025-07-25',
                'total_cost' => 18500.00,
                'notes' => 'Fragile items - handle with care',
                'cargo_details' => [
                    'description' => 'Glassware & Ceramics',
                    'weight_kg' => 800.0,
                    'volume_cbm' => 8.5,
                ],
            ],
            [
                'shipment_number' => 'CSL20250003',
                'consignee' => 'Asia Pacific Logistics',
                'hbl_number' => 'HBL2025003',
                'mbl_number' => 'MBL2025003',
                'invoice_number' => 'INV2025003',
                'quantity_days' => 7,
                'do_pickup_date' => '2025-08-01 09:00:00',
                'weight_kgm' => 2200.0,
                'fcl_type' => '29 Jun',
                'container_arrival' => 'POS LAEMCHABANG V.10223W',
                'customer_id' => $customers->skip(2)->first()->id,
                'vessel_id' => $vessels->where('vessel_name', 'WAN HAI 517')->first()->id,
                'port_of_discharge' => 'Laem Chabang',
                'berth_location' => 'A0',
                'joint_pickup' => 'PUI',
                'customs_entry' => 'NOON',
                'vessel_loading_status' => null,
                'status' => 'new',
                'thai_status' => 'ใหม่',
                'planned_delivery_date' => '2025-08-02',
                'total_cost' => 32000.00,
                'notes' => 'Temperature controlled cargo',
                'cargo_details' => [
                    'description' => 'Pharmaceutical Products',
                    'weight_kg' => 2200.0,
                    'volume_cbm' => 15.8,
                ],
            ],
            [
                'shipment_number' => 'CSL20250004',
                'consignee' => 'Textile Imports Thailand',
                'hbl_number' => 'HBL2025004',
                'mbl_number' => 'MBL2025004',
                'invoice_number' => 'INV2025004',
                'quantity_days' => 4,
                'do_pickup_date' => '2025-08-07 11:00:00',
                'weight_kgm' => 5000.0,
                'fcl_type' => '29 Jun',
                'container_arrival' => 'XIN AN V.515',
                'customer_id' => $customers->skip(3)->first()->id,
                'vessel_id' => $vessels->where('vessel_name', 'SRI SUREE')->first()->id,
                'port_of_discharge' => 'Map Ta Phut',
                'berth_location' => 'B4',
                'joint_pickup' => 'FRANK',
                'customs_entry' => 'ING',
                'vessel_loading_status' => null,
                'status' => 'planning',
                'thai_status' => 'กำลังวางแผน',
                'planned_delivery_date' => '2025-08-08',
                'total_cost' => 15000.00,
                'notes' => 'Bulk textile shipment',
                'cargo_details' => [
                    'description' => 'Cotton Fabric Rolls',
                    'weight_kg' => 5000.0,
                    'volume_cbm' => 45.2,
                ],
            ],
            [
                'shipment_number' => 'CSL20250005',
                'consignee' => 'ABC Import Co., Ltd.',
                'hbl_number' => 'HBL2025005',
                'mbl_number' => 'MBL2025005',
                'invoice_number' => 'INV2025005',
                'quantity_days' => 2,
                'do_pickup_date' => '2025-07-19 13:00:00',
                'weight_kgm' => 650.0,
                'fcl_type' => '15 Jun',
                'container_arrival' => 'KMTC TOKYO V.2508S',
                'customer_id' => $customers->first()->id,
                'vessel_id' => $vessels->first()->id,
                'port_of_discharge' => 'Laem Chabang',
                'berth_location' => 'A0',
                'joint_pickup' => 'PUI',
                'customs_entry' => 'TOON',
                'vessel_loading_status' => 'COMPLETED',
                'status' => 'delivered',
                'thai_status' => 'ส่งมอบแล้ว',
                'planned_delivery_date' => '2025-07-20',
                'actual_delivery_date' => '2025-07-20',
                'total_cost' => 12000.00,
                'notes' => 'Successfully delivered on time',
                'cargo_details' => [
                    'description' => 'Computer Accessories',
                    'weight_kg' => 650.0,
                    'volume_cbm' => 5.2,
                ],
            ],
        ];

        foreach ($shipments as $shipmentData) {
            Shipment::create($shipmentData);
        }

        $this->command->info('Sample data created successfully!');
        $this->command->info('Created: ' . count($customers) . ' customers, ' . count($vessels) . ' vessels, ' . count($shipments) . ' shipments');
    }
}
