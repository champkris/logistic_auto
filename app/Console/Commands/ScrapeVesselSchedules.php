<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VesselSchedule;
use App\Services\BrowserAutomationService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScrapeVesselSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vessel:scrape-schedules
                            {--terminal= : Specific terminal to scrape (optional)}
                            {--test : Test mode - only scrape first page}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape vessel schedules from all supported terminals and store in database';

    /**
     * Terminals that support full schedule scraping (no vessel name required)
     */
    protected $scrapableTerminals = [
        'hutchison' => ['C1', 'C2', 'C3', 'D1'],
        'shipmentlink' => ['SIAM', 'KERRY'],
        'tips' => ['TIPS'],
        // LCB1 requires vessel name, so we'll skip for daily scrape
        // 'lcb1' => ['A0', 'B1', 'B3', 'B4'],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting vessel schedule scraping...');
        $startTime = now();

        // Clean up expired schedules first
        $this->info('🧹 Cleaning up expired schedules...');
        $deleted = VesselSchedule::cleanupExpired();
        $this->info("   Deleted {$deleted} expired records");

        $totalScraped = 0;
        $terminalFilter = $this->option('terminal');

        foreach ($this->scrapableTerminals as $scraper => $terminals) {
            // Skip if terminal filter is set and doesn't match
            if ($terminalFilter && !in_array($terminalFilter, $terminals)) {
                continue;
            }

            $this->info("📡 Scraping {$scraper}...");

            try {
                $count = $this->scrapeTerminal($scraper, $terminals);
                $totalScraped += $count;
                $this->info("   ✅ Scraped {$count} vessels from {$scraper}");
            } catch (\Exception $e) {
                $this->error("   ❌ Failed to scrape {$scraper}: " . $e->getMessage());
                Log::error("Vessel schedule scraping failed for {$scraper}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            if ($this->option('test')) {
                $this->warn('   ⚠️  Test mode - stopping after first terminal');
                break;
            }
        }

        $duration = now()->diffInSeconds($startTime);
        $this->info("✨ Scraping complete! Total: {$totalScraped} vessels in {$duration}s");

        return 0;
    }

    /**
     * Scrape a specific terminal
     */
    protected function scrapeTerminal(string $scraper, array $terminals): int
    {
        $count = 0;

        switch ($scraper) {
            case 'hutchison':
                $count = $this->scrapeHutchison($terminals);
                break;

            case 'shipmentlink':
                $count = $this->scrapeShipmentlink($terminals);
                break;

            case 'tips':
                $count = $this->scrapeTips();
                break;

            default:
                $this->warn("   Unknown scraper: {$scraper}");
        }

        return $count;
    }

    /**
     * Scrape Hutchison terminals (C1, C2, C3, D1)
     */
    protected function scrapeHutchison(array $terminals): int
    {
        $count = 0;
        $automation = new BrowserAutomationService();

        // Hutchison scraper should have a method to get all vessels
        // For now, we'll use a placeholder that calls the Node.js scraper
        foreach ($terminals as $terminal) {
            try {
                $this->line("   Processing {$terminal}...");

                // Call Node.js scraper to get full schedule
                $result = $automation->scrapeHutchisonFullSchedule($terminal);

                if (!$result || !isset($result['vessels']) || !is_array($result['vessels'])) {
                    $this->warn("   No data returned for {$terminal}");
                    continue;
                }

                foreach ($result['vessels'] as $vessel) {
                    $this->storeVesselSchedule([
                        'vessel_name' => $vessel['vessel_name'] ?? '',
                        'voyage_code' => $vessel['voyage'] ?? null,
                        'port_terminal' => $terminal,
                        'berth' => $vessel['berth'] ?? null,
                        'eta' => $vessel['eta'] ?? null,
                        'etd' => $vessel['etd'] ?? null,
                        'cutoff' => null,
                        'opengate' => null,
                        'source' => 'hutchison',
                        'raw_data' => $vessel,
                    ]);
                    $count++;
                }
            } catch (\Exception $e) {
                $this->error("   Error scraping {$terminal}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Scrape Shipmentlink terminals (SIAM, KERRY)
     */
    protected function scrapeShipmentlink(array $terminals): int
    {
        $count = 0;
        $automation = new BrowserAutomationService();

        foreach ($terminals as $terminal) {
            try {
                $this->line("   Processing {$terminal}...");

                $result = $automation->scrapeShipmentlinkFullSchedule($terminal);

                if (!$result || !isset($result['vessels']) || !is_array($result['vessels'])) {
                    $this->warn("   No data returned for {$terminal}");
                    continue;
                }

                foreach ($result['vessels'] as $vessel) {
                    $this->storeVesselSchedule([
                        'vessel_name' => $vessel['vessel_name'] ?? '',
                        'voyage_code' => $vessel['voyage'] ?? null,
                        'port_terminal' => $terminal,
                        'berth' => $vessel['berth'] ?? null,
                        'eta' => $vessel['eta'] ?? null,
                        'etd' => $vessel['etd'] ?? null,
                        'cutoff' => null,
                        'opengate' => null,
                        'source' => 'shipmentlink',
                        'raw_data' => $vessel,
                    ]);
                    $count++;
                }
            } catch (\Exception $e) {
                $this->error("   Error scraping {$terminal}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Scrape TIPS terminal
     */
    protected function scrapeTips(): int
    {
        $count = 0;
        $automation = new BrowserAutomationService();

        try {
            $this->line("   Processing TIPS...");

            $result = $automation->scrapeTipsFullSchedule();

            if (!$result || !isset($result['vessels']) || !is_array($result['vessels'])) {
                $this->warn("   No data returned for TIPS");
                return 0;
            }

            foreach ($result['vessels'] as $vessel) {
                $this->storeVesselSchedule([
                    'vessel_name' => $vessel['vessel_name'] ?? '',
                    'voyage_code' => $vessel['voyage'] ?? null,
                    'port_terminal' => 'TIPS',
                    'berth' => $vessel['berth'] ?? null,
                    'eta' => $vessel['eta'] ?? null,
                    'etd' => $vessel['etd'] ?? null,
                    'cutoff' => null,
                    'opengate' => null,
                    'source' => 'tips',
                    'raw_data' => $vessel,
                ]);
                $count++;
            }
        } catch (\Exception $e) {
            $this->error("   Error scraping TIPS: " . $e->getMessage());
        }

        return $count;
    }

    /**
     * Store or update vessel schedule in database
     */
    protected function storeVesselSchedule(array $data): void
    {
        if (empty($data['vessel_name'])) {
            return;
        }

        // Parse dates
        $eta = $this->parseDate($data['eta']);
        $etd = $this->parseDate($data['etd']);

        // Skip if no valid ETA or if ETA is in the past
        if (!$eta || $eta->isPast()) {
            return;
        }

        // Set expiry to 48 hours from now (schedules are updated daily)
        $expiresAt = now()->addHours(48);

        VesselSchedule::updateOrCreate(
            [
                'vessel_name' => strtoupper(trim($data['vessel_name'])),
                'port_terminal' => $data['port_terminal'],
                'voyage_code' => $data['voyage_code'] ?? null,
            ],
            [
                'berth' => $data['berth'],
                'eta' => $eta,
                'etd' => $etd,
                'cutoff' => $this->parseDate($data['cutoff'] ?? null),
                'opengate' => $this->parseDate($data['opengate'] ?? null),
                'source' => $data['source'],
                'raw_data' => $data['raw_data'] ?? [],
                'scraped_at' => now(),
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Parse date string to Carbon instance
     */
    protected function parseDate($date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }
}
