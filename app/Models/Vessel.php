<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Vessel extends Model
{
    use HasFactory;

    protected $fillable = [
        'vessel_name',
        'voyage_number',
        'eta',
        'actual_arrival',
        'port',
        'status',
        'imo_number',
        'agent',
        'notes',
    ];

    protected $casts = [
        'eta' => 'datetime',
        'actual_arrival' => 'datetime',
    ];

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
