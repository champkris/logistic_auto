<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropdownSetting extends Model
{
    protected $fillable = [
        'field_name',
        'value',
        'label',
        'url',
        'email',
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
     * Get all dropdown options with URLs for a specific field
     */
    public static function getFieldOptionsWithUrls($fieldName, $activeOnly = true)
    {
        $query = static::where('field_name', $fieldName);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(['value', 'label', 'url', 'email'])
                    ->toArray();
    }

    /**
     * Get all dropdown options with emails for a specific field
     */
    public static function getFieldOptionsWithEmails($fieldName, $activeOnly = true)
    {
        $query = static::where('field_name', $fieldName);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')
                    ->orderBy('label')
                    ->get(['value', 'label', 'email'])
                    ->keyBy('value')
                    ->toArray();
    }

    /**
     * Get available field types for configuration
     */
    public static function getConfigurableFields()
    {
        return [
            'shipping_team' => 'ชิ้ปปิ้ง (Shipping Team)',
            'cs_reference' => 'CS (CS Team)',
            'customs_clearance_status' => 'สถานะใบขน (Customs Status)',
            'overtime_status' => 'ล่วงเวลา (Overtime Status)',
            'do_status' => 'สถานะ DO (DO Status)',
            'pickup_location' => 'สถานที่รับ (Pickup Location)',
            'port_terminal' => 'ท่าเรือ (Port Terminal)',
            'quantity_unit' => 'หน่วยจำนวนสินค้า (Quantity Unit)',
        ];
    }
}
