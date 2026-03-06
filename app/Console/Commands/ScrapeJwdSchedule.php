<?php

namespace App\Console\Commands;

use App\Models\DailyScrapeLog;
use App\Models\VesselSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeJwdSchedule extends Command
{
    protected $signature = 'vessel:scrape-jwd {--dry-run}';

    protected $description = 'Scrape JWD vessel schedules via HTTP API and store in database';

    protected const API_URL = 'https://www.dg-net.org/th/service-api/shipping-schedule';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $startTime = microtime(true);

        $this->info('Fetching JWD schedule...');

        try {
            $response = Http::timeout(30)->get(self::API_URL);

            if (!$response->successful()) {
                $this->error("JWD API request failed: HTTP {$response->status()}");
                $this->logScrape('failed', 0, 0, 0, microtime(true) - $startTime, "HTTP {$response->status()}");
                return 1;
            }

            $html = $response->body();
            $rows = $this->parseAllRows($html);

            if (empty($rows)) {
                $this->warn('No vessel rows found in JWD response.');
                $this->logScrape('success', 0, 0, 0, microtime(true) - $startTime);
                return 0;
            }

            // Group arrival + departure rows by vessel name + voyage
            $vessels = $this->mergeArrivalDeparture($rows);

            $this->info("Found " . count($rows) . " rows, merged into " . count($vessels) . " vessels.");

            if ($dryRun) {
                foreach ($vessels as $v) {
                    $eta = $v['eta'] ?? '-';
                    $etd = $v['etd'] ?? '-';
                    $this->line("  [DRY RUN] {$v['vessel_name']} / {$v['voyage_code']} | ETA: {$eta} | ETD: {$etd} | Berth: {$v['berth']}");
                }
                $this->info("[DRY RUN] Would store " . count($vessels) . " vessel schedules.");
                return 0;
            }

            $created = 0;
            $updated = 0;

            foreach ($vessels as $v) {
                $result = $this->storeVesselSchedule($v);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                }
            }

            $duration = microtime(true) - $startTime;
            $this->info("Stored {$created} new, {$updated} updated vessel schedules in " . round($duration, 1) . "s");

            $this->logScrape('success', count($vessels), $created, $updated, $duration);

            return 0;

        } catch (\Exception $e) {
            $this->error("JWD scrape failed: " . $e->getMessage());
            Log::error("JWD scrape failed: " . $e->getMessage());
            $this->logScrape('failed', 0, 0, 0, microtime(true) - $startTime, $e->getMessage());
            return 1;
        }
    }

    protected function parseAllRows(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($dom);

        $rows = [];
        $trNodes = $xpath->query('//tr');

        foreach ($trNodes as $tr) {
            $cells = $xpath->query('td', $tr);

            if ($cells->length < 7) {
                continue;
            }

            $vesselName = trim($cells->item(1)->textContent);
            $voyageIn = trim($cells->item(2)->textContent);
            $voyageOut = trim($cells->item(3)->textContent);
            $arrival = trim($cells->item(4)->textContent);
            $departure = trim($cells->item(5)->textContent);
            $berthRaw = trim($cells->item(6)->textContent);

            // Skip header/empty rows
            if (empty($vesselName) || $vesselName === 'VESSEL NAME') {
                continue;
            }

            $rows[] = [
                'vessel_name' => $vesselName,
                'voyage_in' => $voyageIn,
                'voyage_out' => $voyageOut,
                'arrival' => $arrival,
                'departure' => $departure,
                'berth_raw' => $berthRaw,
            ];
        }

        return $rows;
    }

    protected function mergeArrivalDeparture(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            // Determine voyage code: use whichever is populated
            $voyage = !empty($row['voyage_in']) ? $row['voyage_in'] : $row['voyage_out'];
            $key = strtoupper($row['vessel_name']) . '|' . strtoupper($voyage);

            if (!isset($grouped[$key])) {
                // Extract berth code from "A0-LCMT" -> "A0"
                $berth = $row['berth_raw'];
                if (str_contains($berth, '-')) {
                    $berth = explode('-', $berth, 2)[0];
                }

                $grouped[$key] = [
                    'vessel_name' => $row['vessel_name'],
                    'voyage_code' => $voyage,
                    'eta' => null,
                    'etd' => null,
                    'berth' => $berth,
                    'berth_raw' => $row['berth_raw'],
                ];
            }

            // Arrival row has voyage_in + arrival date
            if (!empty($row['voyage_in']) && !empty($row['arrival'])) {
                $grouped[$key]['eta'] = $this->formatJwdDate($row['arrival']);
            }

            // Departure row has voyage_out + departure date
            if (!empty($row['voyage_out']) && !empty($row['departure'])) {
                $grouped[$key]['etd'] = $this->formatJwdDate($row['departure']);
            }
        }

        return array_values($grouped);
    }

    protected function formatJwdDate(string $dateStr): ?string
    {
        // JWD format: "06 Mar 2026 05:00:00"
        $pattern = '/(\d{1,2})\s+(\w{3})\s+(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2})/';
        if (!preg_match($pattern, $dateStr, $m)) {
            return null;
        }

        $monthMap = [
            'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04',
            'May' => '05', 'Jun' => '06', 'Jul' => '07', 'Aug' => '08',
            'Sep' => '09', 'Oct' => '10', 'Nov' => '11', 'Dec' => '12',
        ];

        $month = $monthMap[$m[2]] ?? null;
        if (!$month) {
            return null;
        }

        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $hour = str_pad($m[4], 2, '0', STR_PAD_LEFT);

        return "{$m[3]}-{$month}-{$day} {$hour}:{$m[5]}:{$m[6]}";
    }

    protected function storeVesselSchedule(array $data): ?string
    {
        $eta = $data['eta'] ? Carbon::parse($data['eta']) : null;
        $etd = $data['etd'] ? Carbon::parse($data['etd']) : null;

        // Skip if no ETA
        if (!$eta) {
            return null;
        }

        // Skip if ETA is more than 1 month in the past
        if ($eta->lt(now()->subMonth())) {
            return null;
        }

        $schedule = VesselSchedule::updateOrCreate(
            [
                'vessel_name' => strtoupper(trim($data['vessel_name'])),
                'port_terminal' => 'JWD',
                'voyage_code' => $data['voyage_code'] ?? null,
            ],
            [
                'berth' => $data['berth'],
                'eta' => $eta,
                'etd' => $etd,
                'cutoff' => null,
                'opengate' => null,
                'source' => 'jwd',
                'raw_data' => $data,
                'scraped_at' => now(),
                'expires_at' => now()->addHours(48),
            ]
        );

        return $schedule->wasRecentlyCreated ? 'created' : 'updated';
    }

    protected function logScrape(
        string $status,
        int $vesselsFound,
        int $created,
        int $updated,
        float $duration,
        ?string $error = null
    ): void {
        DailyScrapeLog::create([
            'terminal' => 'jwd',
            'ports_scraped' => ['JWD'],
            'vessels_found' => $vesselsFound,
            'schedules_created' => $created,
            'schedules_updated' => $updated,
            'status' => $status,
            'error_message' => $error,
            'duration_seconds' => (int) $duration,
        ]);
    }
}
