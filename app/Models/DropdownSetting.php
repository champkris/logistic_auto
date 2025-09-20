<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropdownSetting extends Model
{
    protected $fillable = [
        'field_name',
        'value',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all dropdown options for a specific field
     */
    public static function getFieldOptions($fieldName, $activeOnly = true)
    {
        $query = static::where('field_name', $fieldName);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')
                    ->orderBy('label')
                    ->pluck('label', 'value')
                    ->toArray();
    }

    /**
     * Get available field types for configuration
     */
    public static function getConfigurableFields()
    {
        return [
            'port_of_discharge' => 'ท่าเรือ (Port of Discharge)',
            'shipping_team' => 'ชิ้ปปิ้ง (Shipping Team)',
            'cs_reference' => 'CS (CS Team)',
            'customs_clearance_status' => 'สถานะใบขน (Customs Status)',
            'overtime_status' => 'ล่วงเวลา (Overtime Status)',
            'do_status' => 'สถานะ DO (DO Status)',
            'shipping_line' => 'สายเรือ (Shipping Line)',
            'vsl_payment_status' => 'สถานะชำระเงิน VSL (VSL Payment)',
            'final_status' => 'สถานะสุดท้าย (Final Status)',
            'pickup_location' => 'สถานที่รับ (Pickup Location)',
        ];
    }
}
