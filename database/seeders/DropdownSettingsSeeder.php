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
                ['value' => 'jow', 'label' => 'JOW', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 13],
                ['value' => 'toon', 'label' => 'TOON', 'email' => 'sealcb_import1@easternair.co.th', 'sort_order' => 14],
                ['value' => 'other', 'label' => 'OTHER', 'email' => null, 'sort_order' => 15],
            ],

            // Port Terminal / ท่าเรือ (expanded list with individual ports)
            'port_terminal' => [
                // LCB1 Terminals
                ['value' => 'A0', 'label' => 'A0 (LCB1)', 'url' => 'https://www.lcb1.com/BerthSchedule', 'sort_order' => 1],
                ['value' => 'A3', 'label' => 'A3 (LCB1)', 'url' => 'https://www.lcb1.com/BerthSchedule', 'sort_order' => 2],
                ['value' => 'B1', 'label' => 'B1 (LCB1)', 'url' => 'https://www.lcb1.com/BerthSchedule', 'sort_order' => 3],

                // ShipmentLink Terminal
                ['value' => 'B2', 'label' => 'B2 (ShipmentLink)', 'url' => 'https://ss.shipmentlink.com/tvs2/jsp/TVS2_VesselSchedule.jsp', 'sort_order' => 4],

                // ESCO Terminal
                ['value' => 'B3', 'label' => 'B3 (ESCO)', 'url' => 'https://service.esco.co.th/BerthSchedule', 'sort_order' => 5],

                // TIPS Terminal
                ['value' => 'B4', 'label' => 'B4 (TIPS)', 'url' => 'https://www.tips.co.th/container/shipSched/List', 'sort_order' => 6],

                // LCIT Terminals
                ['value' => 'B5', 'label' => 'B5 (LCIT)', 'url' => 'https://www.lcit.com/home', 'sort_order' => 7],

                // D1 Terminal
                ['value' => 'D1', 'label' => 'D1 (LCB1)', 'url' => 'https://www.lcb1.com/BerthSchedule', 'sort_order' => 8],

                // Hutchison Ports Terminals
                ['value' => 'C1', 'label' => 'C1 (Hutchison Ports)', 'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:13:::::', 'sort_order' => 9],
                ['value' => 'C2', 'label' => 'C2 (Hutchison Ports)', 'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:13:::::', 'sort_order' => 10],
                ['value' => 'C3', 'label' => 'C3 (LCIT)', 'url' => 'https://www.lcit.com/home', 'sort_order' => 11],

                // Special/Commercial Terminals
                ['value' => 'SIAM', 'label' => 'SIAM (Siam Commercial)', 'url' => 'n8n_integration', 'sort_order' => 12],
                ['value' => 'KERRY', 'label' => 'KERRY (Kerry Logistics)', 'url' => 'https://terminaltracking.ksp.kln.com/SearchVesselVisit', 'sort_order' => 13],

                // JWD Terminal
                ['value' => 'JWD', 'label' => 'JWD (DG-NET Terminal)', 'url' => 'https://www.dg-net.org/th/service-shipping', 'sort_order' => 14],

                // Legacy support (grouped terminals)
                ['value' => 'A0B1', 'label' => 'A0B1 (LCB1 - Legacy)', 'url' => 'https://www.lcb1.com/BerthSchedule', 'sort_order' => 15],
                ['value' => 'B5C3', 'label' => 'B5C3 (LCIT - Legacy)', 'url' => 'https://www.lcit.com/home', 'sort_order' => 16],
                ['value' => 'C1C2', 'label' => 'C1C2 (Hutchison - Legacy)', 'url' => 'https://online.hutchisonports.co.th/hptpcs/f?p=114:13:::::', 'sort_order' => 17],
            ],

            // Quantity Unit Type / ประเภทหน่วยจำนวนสินค้า (simplified to match actual usage)
            'quantity_unit' => [
                ['value' => 'CTN', 'label' => 'CTN (Carton)', 'sort_order' => 1],
                ['value' => '20_dry', 'label' => "20'Dry", 'sort_order' => 2],
                ['value' => '40_dry', 'label' => "40'Dry", 'sort_order' => 3],
                ['value' => '40_hc', 'label' => "40'HC", 'sort_order' => 4],
                ['value' => 'lcl', 'label' => 'LCL', 'sort_order' => 5],
                ['value' => 'bag', 'label' => 'Bag', 'sort_order' => 6],
                ['value' => 'box', 'label' => 'Box', 'sort_order' => 7],
                ['value' => 'carton', 'label' => 'Carton', 'sort_order' => 8],
                ['value' => 'package', 'label' => 'Package', 'sort_order' => 9],
                ['value' => 'pallet', 'label' => 'Pallet', 'sort_order' => 10],
                ['value' => 'piece', 'label' => 'Piece', 'sort_order' => 11],
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