<?php

namespace App\Livewire;

use App\Models\Shipment;
use App\Models\Vessel;
use App\Models\Document;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'total_shipments' => Shipment::count(),
            'active_shipments' => Shipment::active()->count(),
            'vessels_arriving_soon' => Vessel::arrivingSoon()->count(),
            'pending_documents' => Document::pending()->count(),
            'overdue_documents' => Document::overdue()->count(),
        ];

        $recent_shipments = Shipment::with(['customer', 'vessel'])
            ->latest()
            ->take(5)
            ->get();

        $vessels_arriving = Vessel::arrivingSoon()
            ->with('shipments')
            ->orderBy('eta')
            ->get();

        $urgent_tasks = [
            'pending_dos' => Document::where('type', 'do')
                ->where('status', 'pending')
                ->count(),
            'customs_clearance' => Shipment::where('status', 'customs_clearance')->count(),
            'ready_for_delivery' => Shipment::where('status', 'ready_for_delivery')->count(),
        ];

        return view('livewire.dashboard', compact(
            'stats', 
            'recent_shipments', 
            'vessels_arriving', 
            'urgent_tasks'
        ))->layout('layouts.app', ['title' => 'Dashboard']);
    }
}
