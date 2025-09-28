<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EtaCheckLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'terminal',
        'vessel_name',
        'voyage_code',
        'scraped_eta',
        'shipment_eta_at_time',
        'tracking_status',
        'vessel_found',
        'voyage_found',
        'raw_response',
        'error_message',
        'initiated_by',
    ];

    protected $casts = [
        'scraped_eta' => 'datetime',
        'shipment_eta_at_time' => 'datetime',
        'vessel_found' => 'boolean',
        'voyage_found' => 'boolean',
        'raw_response' => 'array',
    ];

    /**
     * Get the shipment that owns the ETA check log.
     */
    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Get the user who initiated the check.
     */
    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute()
    {
        return match($this->tracking_status) {
            'on_track' => 'green',
            'delay' => 'red',
            'not_found' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get human readable status text.
     */
    public function getStatusTextAttribute()
    {
        return match($this->tracking_status) {
            'on_track' => 'On Track',
            'delay' => 'Delay',
            'not_found' => 'Not Found',
            default => 'Unknown'
        };
    }

    /**
     * Scope to get recent checks first.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope to get checks for a specific shipment.
     */
    public function scopeForShipment($query, $shipmentId)
    {
        return $query->where('shipment_id', $shipmentId);
    }
}
