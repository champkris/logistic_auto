<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeKerryVessel;
use App\Models\Shipment;
use Illuminate\Console\Command;

class ScrapeKerryVessels extends Command
{
    protected $signature = 'vessel:scrape-kerry {--dry-run}';

    protected $description = 'Dispatch queue jobs to scrape Kerry/KLN vessel schedules';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $shipments = Shipment::with('vessel')
            ->where('status', 'in-progress')
            ->whereIn('port_terminal', ['KLN', 'KERRY'])
            ->whereNotNull('vessel_id')
            ->whereBetween('client_requested_delivery_date', [now()->subMonth(), now()->addMonth()])
            ->get();

        if ($shipments->isEmpty()) {
            $this->info('No active KLN/KERRY shipments found.');
            return 0;
        }

        // Deduplicate by vessel_name + voyage to avoid redundant API calls
        $uniqueVessels = $shipments
            ->filter(fn ($s) => $s->vessel && $s->vessel->name)
            ->unique(fn ($s) => strtoupper($s->vessel->name) . '|' . strtoupper($s->voyage ?? ''))
            ->values();

        if ($uniqueVessels->isEmpty()) {
            $this->info('No shipments with valid vessel data found.');
            return 0;
        }

        $this->info("Found {$shipments->count()} shipments, {$uniqueVessels->count()} unique vessels.");

        $dispatched = 0;

        foreach ($uniqueVessels as $shipment) {
            $vesselName = $shipment->vessel->name;
            $voyage = $shipment->voyage ?? '';
            $portTerminal = $shipment->port_terminal;

            if ($dryRun) {
                $this->line("  [DRY RUN] Would dispatch: {$vesselName} / {$voyage} ({$portTerminal})");
            } else {
                ScrapeKerryVessel::dispatch($vesselName, $voyage, $portTerminal)
                    ->onQueue('kerry-scraper');
                $this->line("  Dispatched: {$vesselName} / {$voyage} ({$portTerminal})");
            }

            $dispatched++;
        }

        $action = $dryRun ? 'Would dispatch' : 'Dispatched';
        $this->info("{$action} {$dispatched} Kerry scrape jobs.");

        return 0;
    }
}
