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
        'esco' => ['B3'], // ESCO is separate website from LCIT
        'lcit' => ['B5', 'C3'], // LCIT API covers both B5 and C3
        // LCB1 requires vessel name, so we'll skip for daily scrape
        // 'lcb1' => ['A0', 'B1', 'B4'],
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

            case 'esco':
                $count = $this->scrapeEsco();
                break;

            case 'lcit':
                $count = $this->scrapeLcit();
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
                    // Use actual berth as port_terminal (e.g., "D1" from "Berth Terminal" column)
                    // If berth is not set, fall back to the terminal parameter
                    $portTerminal = $vessel['berth'] ?? $terminal;

                    $this->storeVesselSchedule([
                        'vessel_name' => $vessel['vessel_name'] ?? '',
                        'voyage_code' => $vessel['voyage'] ?? null,
                        'port_terminal' => $portTerminal,
                        'berth' => $portTerminal,
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
                    'port_terminal' => 'B4', // TIPS is terminal B4
                    'berth' => $vessel['berth'] ?? 'B4',
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
     * Scrape ESCO (B3) terminal
     */
    protected function scrapeEsco(): int
    {
        $count = 0;
        $automation = new BrowserAutomationService();

        try {
            $this->line("   Processing B3 (ESCO)...");

            $result = $automation->scrapeEscoFullSchedule();

            if (!$result || !isset($result['vessels']) || !is_array($result['vessels'])) {
                $this->warn("   No data returned for ESCO");
                return 0;
            }

            foreach ($result['vessels'] as $vessel) {
                $this->storeVesselSchedule([
                    'vessel_name' => $vessel['vessel_name'] ?? '',
                    'voyage_code' => $vessel['voyage'] ?? null,
                    'port_terminal' => 'B3',
                    'berth' => $vessel['berth'] ?? 'B3',
                    'eta' => $vessel['eta'] ?? null,
                    'etd' => $vessel['etd'] ?? null,
                    'cutoff' => $vessel['cutoff'] ?? null,
                    'opengate' => $vessel['opengate'] ?? null,
                    'source' => 'esco',
                    'raw_data' => $vessel,
                ]);
                $count++;
            }
        } catch (\Exception $e) {
            $this->error("   Error scraping ESCO: " . $e->getMessage());
        }

        return $count;
    }

    /**
     * Scrape LCIT (B5) terminal
     */
    protected function scrapeLcit(): int
    {
        $count = 0;
        $automation = new BrowserAutomationService();

        try {
            $this->line("   Processing B5 (LCIT)...");

            $result = $automation->scrapeLcitFullSchedule();

            if (!$result || !isset($result['vessels']) || !is_array($result['vessels'])) {
                $this->warn("   No data returned for LCIT");
                return 0;
            }

            foreach ($result['vessels'] as $vessel) {
                // Use berth as port_terminal (C3, B5, etc.)
                $portTerminal = $vessel['berth'] ?? 'B5';

                $this->storeVesselSchedule([
                    'vessel_name' => $vessel['vessel_name'] ?? '',
                    'voyage_code' => $vessel['voyage'] ?? null,
                    'port_terminal' => $portTerminal,
                    'berth' => $portTerminal,
                    'eta' => $vessel['eta'] ?? null,
                    'etd' => $vessel['etd'] ?? null,
                    'cutoff' => $vessel['cutoff'] ?? null,
                    'opengate' => $vessel['opengate'] ?? null,
                    'source' => 'lcit',
                    'raw_data' => $vessel,
                ]);
                $count++;
            }
        } catch (\Exception $e) {
            $this->error("   Error scraping LCIT: " . $e->getMessage());
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

        // Skip if no valid ETA
        if (!$eta) {
            return;
        }

        // Skip if ETA is more than 1 month in the past
        $oneMonthAgo = now()->subMonth();
        if ($eta->lt($oneMonthAgo)) {
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
            // Try LCIT format first (04 OCT 25/21:00)
            if (preg_match('/^(\d{1,2})\s+([A-Z]{3})\s+(\d{2})\/(\d{2}):(\d{2})/', $date, $matches)) {
                $day = (int)$matches[1];
                $month = $matches[2];
                $year = (int)$matches[3];
                $hour = (int)$matches[4];
                $minute = (int)$matches[5];

                $monthMap = [
                    'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
                    'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12
                ];

                if (isset($monthMap[$month])) {
                    // Handle 2-digit year (25 = 2025, 98 = 1998)
                    $fullYear = $year < 50 ? 2000 + $year : 1900 + $year;
                    return Carbon::create($fullYear, $monthMap[$month], $day, $hour, $minute);
                }
            }

            // Try common Asian/European date formats (DD/MM/YYYY)
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $date)) {
                return Carbon::createFromFormat('d/m/Y H:i', $date)
                    ?? Carbon::createFromFormat('d/m/Y', $date);
            }

            // Fallback to Carbon's auto-detection for other formats
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }
}
