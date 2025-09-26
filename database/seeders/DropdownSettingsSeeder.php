<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DropdownSetting;

class DropdownSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [

            // Shipping Team / ชิ้ปปิ้ง
            'shipping_team' => [
                ['value' => 'tub', 'label' => 'TUB', 'sort_order' => 1],
                ['value' => 'gus', 'label' => 'GUS', 'sort_order' => 2],
                ['value' => 'beer', 'label' => 'BEER', 'sort_order' => 3],
                ['value' => 'frank', 'label' => 'FRANK', 'sort_order' => 4],
                ['value' => 'mon', 'label' => 'MON', 'sort_order' => 5],
                ['value' => 'aof', 'label' => 'AOF', 'sort_order' => 6],
                ['value' => 'aofkom', 'label' => 'AOFKOM', 'sort_order' => 7],
                ['value' => 'aofcho', 'label' => 'AOFCHO', 'sort_order' => 8],
                ['value' => 'gafiw', 'label' => 'GAFIW', 'sort_order' => 9],
                ['value' => 'other', 'label' => 'OTHER', 'sort_order' => 10],
            ],

            // Customs Clearance Status / สถานะใบขน
            'customs_clearance_status' => [
                ['value' => 'pending', 'label' => 'ยังไม่ได้ใบขน', 'sort_order' => 1],
                ['value' => 'received', 'label' => 'ได้ใบขนแล้ว', 'sort_order' => 2],
                ['value' => 'processing', 'label' => 'กำลังดำเนินการ', 'sort_order' => 3],
            ],

            // Overtime Status / ล่วงเวลา (OT)
            'overtime_status' => [
                ['value' => 'none', 'label' => 'ไม่มี OT', 'sort_order' => 1],
                ['value' => 'ot1', 'label' => 'OT 1 ช่วง', 'sort_order' => 2],
                ['value' => 'ot2', 'label' => 'OT 2 ช่วง', 'sort_order' => 3],
                ['value' => 'ot3', 'label' => 'OT 3 ช่วง', 'sort_order' => 4],
            ],

            // DO Status / สถานะ DO
            'do_status' => [
                ['value' => 'pending', 'label' => 'ไม่ได้รับ', 'sort_order' => 1],
                ['value' => 'received', 'label' => 'ได้รับ', 'sort_order' => 2],
                ['value' => 'processing', 'label' => 'กำลังดำเนินการ', 'sort_order' => 3],
            ],



            // Pickup Location / สถานที่รับ
            'pickup_location' => [
                ['value' => 'warehouse_a', 'label' => 'คลังสินค้า A', 'sort_order' => 1],
                ['value' => 'warehouse_b', 'label' => 'คลังสินค้า B', 'sort_order' => 2],
                ['value' => 'port_office', 'label' => 'สำนักงานท่าเรือ', 'sort_order' => 3],
                ['value' => 'customer_location', 'label' => 'สถานที่ลูกค้า', 'sort_order' => 4],
                ['value' => 'terminal_gate', 'label' => 'ประตูเทอร์มินัล', 'sort_order' => 5],
            ],

            // CS Team / ทีม CS
            'cs_reference' => [
                ['value' => 'bow', 'label' => 'BOW', 'email' => 'sealcb_import2@easternair.co.th', 'sort_order' => 1],
                ['value' => 'meena', 'label' => 'MEENA', 'email' => 'sealcb_import3@easternair.co.th', 'sort_order' => 2],
                ['value' => 'mew', 'label' => 'MEW', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 3],
                ['value' => 'tem', 'label' => 'TEM', 'email' => 'nattapon.bun@easternair.co.th', 'sort_order' => 4],
                ['value' => 'kartoon', 'label' => 'KARTOON', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 5],
                ['value' => 'kaimook', 'label' => 'KAIMOOK', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 6],
                ['value' => 'nui', 'label' => 'NUI', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 7],
                ['value' => 'noey', 'label' => 'NOEY', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 8],
                ['value' => 'may', 'label' => 'MAY', 'email' => 'sealcb_import4@easternair.co.th', 'sort_order' => 9],
                ['value' => 'noon', 'label' => 'NOON', 'email' => 'sealcb_import4@easternair.co.th', 'sort_order' => 10],
                ['value' => 'ing', 'label' => 'ING', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 11],
                ['value' => 'aii', 'label' => 'Aii', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 12],
                ['value' => 'other', 'label' => 'OTHER', 'email' => null, 'sort_order' => 13],
            ],

            // Port Terminal / ท่าเรือ (using VesselTrackingService codes)
            'port_terminal' => [
                ['value' => 'C1C2', 'label' => 'C1C2 (Hutchison Ports)', 'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:17:6927160550678:::::', 'sort_order' => 1],
                ['value' => 'B4', 'label' => 'B4 (TIPS)', 'url' => 'https://www.tips.co.th/container/shipSched/List', 'sort_order' => 2],
                ['value' => 'B5C3', 'label' => 'B5C3 (LCIT)', 'url' => 'https://www.lcit.com/home', 'sort_order' => 3],
                ['value' => 'B3', 'label' => 'B3 (ESCO)', 'url' => 'https://service.esco.co.th/BerthSchedule', 'sort_order' => 4],
                ['value' => 'A0B1', 'label' => 'A0B1 (LCB1)', 'url' => 'https://www.lcb1.com/BerthSchedule', 'sort_order' => 5],
                ['value' => 'B2', 'label' => 'B2 (ShipmentLink)', 'url' => 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', 'sort_order' => 6],
                ['value' => 'SIAM', 'label' => 'SIAM (Siam Commercial)', 'url' => 'n8n_integration', 'sort_order' => 7],
                ['value' => 'KERRY', 'label' => 'KERRY (Kerry Logistics)', 'url' => 'https://terminaltracking.ksp.kln.com/SearchVesselVisit', 'sort_order' => 8],
            ],

            // Quantity Unit Type / ประเภทหน่วยจำนวนสินค้า
            'quantity_unit' => [
                ['value' => '20_dry', 'label' => "20'Dry", 'sort_order' => 1],
                ['value' => '20_fr', 'label' => "20'FR", 'sort_order' => 2],
                ['value' => '20_open_top', 'label' => "20'Open top", 'sort_order' => 3],
                ['value' => '20_rf', 'label' => "20'RF", 'sort_order' => 4],
                ['value' => '40_dry', 'label' => "40'Dry", 'sort_order' => 5],
                ['value' => '40_fr', 'label' => "40'FR", 'sort_order' => 6],
                ['value' => '40_hc', 'label' => "40'HC", 'sort_order' => 7],
                ['value' => '40_open_top', 'label' => "40'Open top", 'sort_order' => 8],
                ['value' => '40_rf', 'label' => "40'RF", 'sort_order' => 9],
                ['value' => '45_hc', 'label' => "45'HC", 'sort_order' => 10],
                ['value' => 'air', 'label' => 'AIR', 'sort_order' => 11],
                ['value' => 'iso_tank', 'label' => 'ISO Tank', 'sort_order' => 12],
                ['value' => 'lcl', 'label' => 'LCL', 'sort_order' => 13],
                ['value' => 'lg_40hq', 'label' => 'LG-40HQ', 'sort_order' => 14],
                ['value' => 'lgeth_an8_40hq', 'label' => 'LGETH(AN8)-40HQ', 'sort_order' => 15],
                ['value' => 'bag', 'label' => 'Bag', 'sort_order' => 16],
                ['value' => 'bottle', 'label' => 'Bottle', 'sort_order' => 17],
                ['value' => 'box', 'label' => 'Box', 'sort_order' => 18],
                ['value' => 'can', 'label' => 'Can', 'sort_order' => 19],
                ['value' => 'case', 'label' => 'Case', 'sort_order' => 20],
                ['value' => 'coil', 'label' => 'Coil', 'sort_order' => 21],
                ['value' => 'crate', 'label' => 'Crate', 'sort_order' => 22],
                ['value' => 'cup', 'label' => 'Cup', 'sort_order' => 23],
                ['value' => 'carton', 'label' => 'Carton', 'sort_order' => 24],
                ['value' => 'package', 'label' => 'Package', 'sort_order' => 25],
                ['value' => 'pallet', 'label' => 'Pallet', 'sort_order' => 26],
                ['value' => 'sheet', 'label' => 'Sheet', 'sort_order' => 27],
                ['value' => 'piece', 'label' => 'Piece', 'sort_order' => 28],
            ],
        ];

        foreach ($settings as $fieldName => $options) {
            foreach ($options as $option) {
                DropdownSetting::updateOrCreate(
                    [
                        'field_name' => $fieldName,
                        'value' => $option['value'],
                    ],
                    [
                        'label' => $option['label'],
                        'url' => $option['url'] ?? null,
                        'email' => $option['email'] ?? null,
                        'sort_order' => $option['sort_order'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}