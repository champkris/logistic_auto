<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class VesselSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'vessel_name',
        'voyage_code',
        'port_terminal',
        'berth',
        'eta',
        'etd',
        'cutoff',
        'opengate',
        'source',
        'raw_data',
        'scraped_at',
        'expires_at',
    ];

    protected $casts = [
        'eta' => 'datetime',
        'etd' => 'datetime',
        'cutoff' => 'datetime',
        'opengate' => 'datetime',
        'scraped_at' => 'datetime',
        'expires_at' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Scope to get only fresh (non-expired) schedules
     */
    public function scopeFresh($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get schedules for a specific vessel
     */
    public function scopeForVessel($query, string $vesselName)
    {
        return $query->where('vessel_name', 'LIKE', '%' . $vesselName . '%');
    }

    /**
     * Scope to get schedules for a specific port
     */
    public function scopeForPort($query, string $portTerminal)
    {
        return $query->where('port_terminal', $portTerminal);
    }

    /**
     * Scope to get schedules with future ETA or within last month
     */
    public function scopeFutureEta($query)
    {
        return $query->where('eta', '>', now()->subMonth());
    }

    /**
     * Scope to get schedules for current year
     */
    public function scopeCurrentYear($query)
    {
        return $query->whereYear('eta', now()->year);
    }

    /**
     * Find vessel schedule by name and optional port and voyage
     */
    public static function findVessel(string $vesselName, ?string $portTerminal = null, ?string $voyageCode = null)
    {
        $query = static::query()
            ->fresh()
            ->forVessel($vesselName)
            ->futureEta()
            ->currentYear()
            ->orderBy('eta', 'asc');

        if ($portTerminal) {
            $query->forPort($portTerminal);
        }

        if ($voyageCode) {
            $query->where('voyage_code', $voyageCode);
        }

        return $query->first();
    }

    /**
     * Find all schedules for a vessel across all ports
     */
    public static function findVesselAllPorts(string $vesselName)
    {
        return static::query()
            ->fresh()
            ->forVessel($vesselName)
            ->futureEta()
            ->currentYear()
            ->orderBy('eta', 'asc')
            ->get();
    }

    /**
     * Clean up expired schedules
     */
    public static function cleanupExpired()
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * Check if data is stale and needs refresh
     */
    public function isStale(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * Check if ETA is in the past
     */
    public function isPastEta(): bool
    {
        return $this->eta && $this->eta < now();
    }
}
