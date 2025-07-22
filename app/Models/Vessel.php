<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Services\VesselNameParser;

class Vessel extends Model
{
    use HasFactory;

    protected $fillable = [
        'vessel_name',
        'name',  // Added for API compatibility
        'voyage_number',
        'eta',
        'actual_arrival',
        'port',
        'status',
        'imo_number',
        'agent',
        'notes',
        'full_vessel_name', // Add this for storing original input
        'metadata',  // Added for automation data
        'last_scraped_at',  // Added for tracking scrape times
        'scraping_source',  // Added for tracking data source
    ];

    protected $casts = [
        'eta' => 'datetime',
        'actual_arrival' => 'datetime',
        'last_scraped_at' => 'datetime',  // Added for automation
        'metadata' => 'array',  // Added for JSON metadata storage
    ];

    /**
     * Automatically parse vessel name when setting full_vessel_name
     */
    public function setFullVesselNameAttribute($value)
    {
        if (!empty($value)) {
            $parsed = VesselNameParser::parse($value);
            
            $this->attributes['full_vessel_name'] = $value;
            $this->attributes['vessel_name'] = $parsed['vessel_name'];
            $this->attributes['voyage_number'] = $parsed['voyage_code'];
        }
    }

    /**
     * Get the full vessel name for display
     */
    public function getFullVesselDisplayAttribute()
    {
        if (!empty($this->full_vessel_name)) {
            return $this->full_vessel_name;
        }
        
        return VesselNameParser::formatForDisplay($this->vessel_name, $this->voyage_number);
    }

    /**
     * Parse and set vessel name from any input
     */
    public function parseAndSetVesselName($fullVesselName)
    {
        $parsed = VesselNameParser::parse($fullVesselName);
        
        $this->full_vessel_name = $fullVesselName;
        $this->vessel_name = $parsed['vessel_name'];
        $this->voyage_number = $parsed['voyage_code'];
        
        return $parsed;
    }

    /**
     * Get vessel name for ETA tracking (just the vessel part)
     */
    public function getVesselNameForTracking()
    {
        return $this->vessel_name;
    }

    /**
     * Get voyage code for tracking (in_voy column equivalent)
     */
    public function getVoyageCodeForTracking()
    {
        return $this->voyage_number;
    }

    /**
     * Get the shipments for the vessel.
     */
    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Check if vessel is delayed.
     */
    public function isDelayed()
    {
        return $this->eta && Carbon::now()->greaterThan($this->eta) && $this->status !== 'arrived';
    }

    /**
     * Get vessels arriving soon (within 48 hours).
     */
    public function scopeArrivingSoon($query)
    {
        return $query->where('eta', '>', Carbon::now())
                    ->where('eta', '<=', Carbon::now()->addHours(48))
                    ->where('status', 'scheduled');
    }

    /**
     * Get vessels by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
