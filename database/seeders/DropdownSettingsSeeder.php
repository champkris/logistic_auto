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
                ['value' => 'pui', 'label' => 'PUI', 'sort_order' => 1],
                ['value' => 'frank', 'label' => 'FRANK', 'sort_order' => 2],
                ['value' => 'gus', 'label' => 'GUS', 'sort_order' => 3],
                ['value' => 'mon', 'label' => 'MON', 'sort_order' => 4],
                ['value' => 'noon', 'label' => 'NOON', 'sort_order' => 5],
                ['value' => 'toon', 'label' => 'TOON', 'sort_order' => 6],
                ['value' => 'ing', 'label' => 'ING', 'sort_order' => 7],
                ['value' => 'jow', 'label' => 'JOW', 'sort_order' => 8],
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
                ['value' => 'bow', 'label' => 'BOW', 'sort_order' => 1],
                ['value' => 'noon', 'label' => 'NOON', 'sort_order' => 2],
                ['value' => 'toon', 'label' => 'TOON', 'sort_order' => 3],
                ['value' => 'ing', 'label' => 'ING', 'sort_order' => 4],
                ['value' => 'jow', 'label' => 'JOW', 'sort_order' => 5],
                ['value' => 'frank', 'label' => 'FRANK', 'sort_order' => 6],
                ['value' => 'pui', 'label' => 'PUI', 'sort_order' => 7],
                ['value' => 'mon', 'label' => 'MON', 'sort_order' => 8],
            ],

            // Port Terminal / ท่าเรือ
            'port_terminal' => [
                ['value' => 'A0', 'label' => 'A0', 'sort_order' => 1],
                ['value' => 'A3', 'label' => 'A3', 'sort_order' => 2],
                ['value' => 'B1', 'label' => 'B1', 'sort_order' => 3],
                ['value' => 'B3', 'label' => 'B3', 'sort_order' => 4],
                ['value' => 'B4', 'label' => 'B4', 'sort_order' => 5],
                ['value' => 'C1', 'label' => 'C1', 'sort_order' => 6],
                ['value' => 'C3', 'label' => 'C3', 'sort_order' => 7],
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
                        'sort_order' => $option['sort_order'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}