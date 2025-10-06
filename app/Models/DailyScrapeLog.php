<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyScrapeLog extends Model
{
    protected $fillable = [
        'terminal',
        'ports_scraped',
        'vessels_found',
        'schedules_created',
        'schedules_updated',
        'status',
        'error_message',
        'duration_seconds',
    ];

    protected $casts = [
        'ports_scraped' => 'array',
        'vessels_found' => 'integer',
        'schedules_created' => 'integer',
        'schedules_updated' => 'integer',
        'duration_seconds' => 'integer',
    ];

    /**
     * Scope to get recent logs first
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter by terminal
     */
    public function scopeForTerminal($query, $terminal)
    {
        return $query->where('terminal', $terminal);
    }

    /**
     * Scope to filter by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'success' => 'green',
            'failed' => 'red',
            'partial' => 'yellow',
            default => 'gray'
        };
    }
}
