<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'name' => 'สมชาย ใจดี',
                'company' => 'บริษัท ใจดี อิมปอร์ต จำกัด',
                'email' => 'somchai@jaidee-import.com',
                'phone' => '02-123-4567',
                'address' => '123 ถนนสุขุมวิท แขวงคลองเตย เขตคลองเตย กรุงเทพฯ 10110',
                'notification_preferences' => [
                    'email' => true,
                    'sms' => false,
                    'daily_update' => true,
                    'urgent_only' => false
                ]
            ],
            [
                'name' => 'วิไล รุ่งเรือง',
                'company' => 'บริษัท รุ่งเรือง เทรดดิ้ง จำกัด',
                'email' => 'wilai@rungrueng.co.th',
                'phone' => '02-987-6543',
                'address' => '456 ถนนลาดพร้าว แขวงจตุจักร เขตจตุจักร กรุงเทพฯ 10900',
                'notification_preferences' => [
                    'email' => true,
                    'sms' => true,
                    'daily_update' => true,
                    'urgent_only' => false
                ]
            ],
            [
                'name' => 'ประสิทธิ์ ก้าวหน้า',
                'company' => 'บริษัท ก้าวหน้า ลอจิสติกส์ จำกัด',
                'email' => 'prasit@kaona-logistics.com',
                'phone' => '02-555-7890',
                'address' => '789 ถนนพหลโยธิน แขวงสามเสนใน เขตพญาไท กรุงเทพฯ 10400',
                'notification_preferences' => [
                    'email' => true,
                    'sms' => false,
                    'daily_update' => false,
                    'urgent_only' => true
                ]
            ]
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
}
