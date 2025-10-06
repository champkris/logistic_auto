<?php

namespace App\Livewire;

use App\Models\EtaCheckSchedule;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ScheduleManager extends Component
{
    use WithPagination;

    // Form properties
    public $name = '';
    public $schedule_type = 'eta_check';
    public $check_time = '';
    public $description = '';
    public $is_active = true;
    public $days_of_week = [];

    // Modal state
    public $showModal = false;
    public $editingSchedule = null;

    // Tab state
    public $activeTab = 'schedules'; // schedules, scrape_reports, eta_reports

    // Day options for checkboxes
    public $dayOptions = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday'
    ];

    protected $rules = [
        'name' => 'required|string|max:255',
        'schedule_type' => 'required|in:vessel_scrape,eta_check',
        'check_time' => 'required|date_format:H:i',
        'description' => 'nullable|string|max:500',
        'is_active' => 'boolean',
        'days_of_week' => 'nullable|array',
        'days_of_week.*' => 'integer|min:1|max:7',
    ];

    public function mount()
    {
        // Initialize with common presets
        $this->check_time = '08:00';
    }

    public function openModal()
    {
        $this->resetForm();
        $this->editingSchedule = null;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function edit($scheduleId)
    {
        $schedule = EtaCheckSchedule::findOrFail($scheduleId);

        $this->editingSchedule = $schedule;
        $this->name = $schedule->name;
        $this->schedule_type = $schedule->schedule_type;
        $this->check_time = $schedule->check_time->format('H:i');
        $this->description = $schedule->description;
        $this->is_active = $schedule->is_active;
        $this->days_of_week = $schedule->days_of_week ?? [];

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'schedule_type' => $this->schedule_type,
            'check_time' => $this->check_time,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'days_of_week' => empty($this->days_of_week) ? null : $this->days_of_week,
            'created_by' => Auth::id(),
        ];

        if ($this->editingSchedule) {
            $this->editingSchedule->update($data);
            $this->editingSchedule->calculateNextRun();
            $message = 'Schedule updated successfully!';
        } else {
            $schedule = EtaCheckSchedule::create($data);
            $schedule->calculateNextRun();
            $message = 'Schedule created successfully!';
        }

        $this->dispatch('success', message: $message);
        $this->closeModal();
        $this->resetPage();
    }

    public function delete($scheduleId)
    {
        EtaCheckSchedule::findOrFail($scheduleId)->delete();
        $this->dispatch('success', message: 'Schedule deleted successfully!');
        $this->resetPage();
    }

    public function toggleActive($scheduleId)
    {
        $schedule = EtaCheckSchedule::findOrFail($scheduleId);
        $schedule->update(['is_active' => !$schedule->is_active]);
        $schedule->calculateNextRun();

        $status = $schedule->is_active ? 'activated' : 'deactivated';
        $this->dispatch('success', message: "Schedule {$status} successfully!");
    }

    public function runNow($scheduleId)
    {
        $schedule = EtaCheckSchedule::findOrFail($scheduleId);

        if ($schedule->schedule_type === 'vessel_scrape') {
            // Run vessel scraping command
            \Illuminate\Support\Facades\Artisan::call('vessel:scrape-schedules');
            $message = 'Vessel scraping initiated! This may take a few minutes.';
        } else {
            // Run ETA check command
            \Illuminate\Support\Facades\Artisan::call('shipments:check-eta', [
                '--schedule-id' => $schedule->id,
                '--limit' => 50,
                '--delay' => 5 // Shorter delay for manual execution
            ]);
            $message = 'ETA check initiated! Results will appear in the shipment history.';
        }

        $schedule->markAsExecuted();

        $this->dispatch('success', message: $message);
    }

    public function runAllEtaCheck()
    {
        // Redirect to the ETA check report page
        return $this->redirect(route('eta-check-report'));
    }

    public function setQuickSchedule($preset)
    {
        switch ($preset) {
            case 'business_hours':
                $this->name = 'Business Hours Check';
                $this->check_time = '09:00';
                $this->days_of_week = [1, 2, 3, 4, 5]; // Weekdays
                $this->description = 'Check ETA during business hours, weekdays only';
                break;
            case 'twice_daily':
                $this->name = 'Morning Check';
                $this->check_time = '08:00';
                $this->days_of_week = [];
                $this->description = 'Daily morning ETA check';
                break;
            case 'evening':
                $this->name = 'Evening Check';
                $this->check_time = '18:00';
                $this->days_of_week = [];
                $this->description = 'Daily evening ETA check';
                break;
        }
    }

    private function resetForm()
    {
        $this->name = '';
        $this->schedule_type = 'eta_check';
        $this->check_time = '08:00';
        $this->description = '';
        $this->is_active = true;
        $this->days_of_week = [];
    }

    /**
     * Switch tabs
     */
    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    /**
     * Get scrape reports
     */
    public function getScrapeReportsProperty()
    {
        return \App\Models\DailyScrapeLog::with([])
            ->recent()
            ->paginate(20, ['*'], 'scrape_page');
    }

    /**
     * Get ETA check reports
     */
    public function getEtaReportsProperty()
    {
        return \App\Models\EtaCheckLog::with(['shipment', 'initiatedBy'])
            ->recent()
            ->paginate(20, ['*'], 'eta_page');
    }

    public function render()
    {
        $schedules = EtaCheckSchedule::with('creator')
            ->orderBy('check_time')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.schedule-manager', [
            'schedules' => $schedules,
            'scrapeReports' => $this->scrapeReports,
            'etaReports' => $this->etaReports,
        ])->layout('layouts.app', ['title' => 'ETA Check Schedules']);
    }
}
