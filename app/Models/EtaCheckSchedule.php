<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EtaCheckSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'schedule_type',
        'check_time',
        'is_active',
        'days_of_week',
        'description',
        'last_run_at',
        'next_run_at',
        'created_by',
    ];

    protected $casts = [
        'check_time' => 'datetime:H:i:s',
        'is_active' => 'boolean',
        'days_of_week' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /**
     * Get the user who created this schedule.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active schedules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get vessel scrape schedules.
     */
    public function scopeVesselScrape($query)
    {
        return $query->where('schedule_type', 'vessel_scrape');
    }

    /**
     * Scope to get ETA check schedules.
     */
    public function scopeEtaCheck($query)
    {
        return $query->where('schedule_type', 'eta_check');
    }

    /**
     * Scope to get schedules that should run now.
     */
    public function scopeDueForExecution($query)
    {
        return $query->active()
            ->where('next_run_at', '<=', now())
            ->orWhere('next_run_at', null);
    }

    /**
     * Check if this schedule should run at the current time.
     */
    public function shouldRunNow()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        $scheduleTime = Carbon::createFromFormat('H:i:s', $this->check_time->format('H:i:s'));

        // Check if it's the right time (within 1 minute window)
        $timeDiff = abs($now->diffInMinutes($scheduleTime->setDate($now->year, $now->month, $now->day)));
        if ($timeDiff > 1) {
            return false;
        }

        // Check if already ran today
        if ($this->last_run_at && $this->last_run_at->isToday()) {
            return false;
        }

        // Check day of week restriction
        if ($this->days_of_week && !in_array($now->dayOfWeek, $this->days_of_week)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate and update the next run time.
     */
    public function calculateNextRun()
    {
        if (!$this->is_active) {
            $this->next_run_at = null;
            return;
        }

        $now = now();
        $scheduleTime = Carbon::createFromFormat('H:i:s', $this->check_time->format('H:i:s'));
        $nextRun = $scheduleTime->setDate($now->year, $now->month, $now->day);

        // If time has passed today, schedule for next valid day
        if ($nextRun->isPast()) {
            $nextRun->addDay();
        }

        // Handle day of week restrictions
        if ($this->days_of_week) {
            while (!in_array($nextRun->dayOfWeek, $this->days_of_week)) {
                $nextRun->addDay();
            }
        }

        $this->next_run_at = $nextRun;
        $this->save();
    }

    /**
     * Mark this schedule as executed.
     */
    public function markAsExecuted()
    {
        $this->last_run_at = now();
        $this->calculateNextRun();
        $this->save();
    }

    /**
     * Get human readable schedule description.
     */
    public function getScheduleDescriptionAttribute()
    {
        $time = $this->check_time->format('H:i');

        if (!$this->days_of_week) {
            return "Daily at {$time}";
        }

        $dayNames = [
            1 => 'Mon', 2 => 'Tue', 3 => 'Wed',
            4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'
        ];

        $days = array_map(fn($day) => $dayNames[$day], $this->days_of_week);

        if (count($days) === 7) {
            return "Daily at {$time}";
        }

        if ($this->days_of_week === [1, 2, 3, 4, 5]) {
            return "Weekdays at {$time}";
        }

        return implode(', ', $days) . " at {$time}";
    }

    /**
     * Get the status color for display.
     */
    public function getStatusColorAttribute()
    {
        if (!$this->is_active) {
            return 'gray';
        }

        if ($this->last_run_at && $this->last_run_at->isToday()) {
            return 'green';
        }

        return 'blue';
    }
}
