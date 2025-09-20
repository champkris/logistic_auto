<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DropdownSetting;

class DropdownSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Port of Discharge / ท่าเรือ
            'port_of_discharge' => [
                ['value' => 'lcb1', 'label' => 'Laem Chabang Terminal 1', 'sort_order' => 1],
                ['value' => 'lcb2', 'label' => 'Laem Chabang Terminal 2', 'sort_order' => 2],
                ['value' => 'lcb3', 'label' => 'Laem Chabang Terminal 3', 'sort_order' => 3],
                ['value' => 'bkk', 'label' => 'Bangkok Port', 'sort_order' => 4],
                ['value' => 'sriracha', 'label' => 'Sriracha Port', 'sort_order' => 5],
                ['value' => 'mapphla', 'label' => 'Map Pha La Port', 'sort_order' => 6],
            ],

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

            // Shipping Line / สายเรือ
            'shipping_line' => [
                ['value' => 'seagreen', 'label' => 'SEAGREEN ร้านลุง', 'sort_order' => 1],
                ['value' => 'intergis', 'label' => 'INTERGIS', 'sort_order' => 2],
                ['value' => 'evergreen', 'label' => 'Evergreen', 'sort_order' => 3],
                ['value' => 'cosco', 'label' => 'COSCO Shipping', 'sort_order' => 4],
                ['value' => 'msc', 'label' => 'MSC', 'sort_order' => 5],
                ['value' => 'maersk', 'label' => 'Maersk Line', 'sort_order' => 6],
                ['value' => 'hapag', 'label' => 'Hapag-Lloyd', 'sort_order' => 7],
                ['value' => 'cma', 'label' => 'CMA CGM', 'sort_order' => 8],
            ],

            // VSL Payment Status / สถานะชำระเงิน VSL
            'vsl_payment_status' => [
                ['value' => 'pending', 'label' => 'รอชำระ', 'sort_order' => 1],
                ['value' => 'paid', 'label' => 'ชำระแล้ว', 'sort_order' => 2],
                ['value' => 'partial', 'label' => 'ชำระบางส่วน', 'sort_order' => 3],
                ['value' => 'overdue', 'label' => 'เกินกำหนด', 'sort_order' => 4],
            ],

            // Final Status / สถานะสุดท้าย
            'final_status' => [
                ['value' => 'completed', 'label' => 'เสร็จสิ้น', 'sort_order' => 1],
                ['value' => 'in_progress', 'label' => 'กำลังดำเนินการ', 'sort_order' => 2],
                ['value' => 'pending', 'label' => 'รอดำเนินการ', 'sort_order' => 3],
                ['value' => 'cancelled', 'label' => 'ยกเลิก', 'sort_order' => 4],
                ['value' => 'on_hold', 'label' => 'พักไว้', 'sort_order' => 5],
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