<?php

namespace App\Jobs;

use App\Models\DailyScrapeLog;
use App\Models\VesselSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeKerryVessel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = 30;

    public function __construct(
        public string $vesselName,
        public string $voyageCode,
        public string $portTerminal = 'KLN'
    ) {}

    public function middleware(): array
    {
        return [new RateLimited('kerry-api')];
    }

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            $html = $this->callKerryApi();

            if ($html === null) {
                $this->logScrape('failed', 0, 0, 0, microtime(true) - $startTime, 'API request failed');
                return;
            }

            $rows = $this->parseKerryHtml($html);

            $created = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $eta = $this->parseKerryDate($row['eta']);

                if (!$eta) {
                    continue;
                }

                // Skip if ETA is more than 1 month in the past
                if ($eta->lt(now()->subMonth())) {
                    continue;
                }

                $schedule = VesselSchedule::updateOrCreate(
                    [
                        'vessel_name' => strtoupper(trim($row['vessel_name'])),
                        'port_terminal' => $this->portTerminal,
                        'voyage_code' => $row['voyage_code'] ?? null,
                    ],
                    [
                        'berth' => null,
                        'eta' => $eta,
                        'etd' => $this->parseKerryDate($row['etd']),
                        'cutoff' => $this->parseKerryDate($row['closing_time']),
                        'opengate' => $this->parseKerryDate($row['open_gate']),
                        'source' => 'kerry',
                        'raw_data' => $row,
                        'scraped_at' => now(),
                        'expires_at' => now()->addHours(48),
                    ]
                );

                if ($schedule->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            Log::info("Kerry scrape completed for {$this->vesselName}", [
                'vessel' => $this->vesselName,
                'voyage' => $this->voyageCode,
                'rows_parsed' => count($rows),
                'created' => $created,
                'updated' => $updated,
            ]);

            $this->logScrape('success', count($rows), $created, $updated, microtime(true) - $startTime);

        } catch (\Exception $e) {
            Log::error("Kerry scrape failed for {$this->vesselName}: " . $e->getMessage());
            $this->logScrape('failed', 0, 0, 0, microtime(true) - $startTime, $e->getMessage());
            throw $e;
        }
    }

    protected function callKerryApi(): ?string
    {
        $url = 'https://terminaltracking.ksp.kln.com/SearchVesselVisit/List';
        $queryParams = [
            'PARM_VESSELNAME' => strtolower(trim($this->vesselName)),
            'PARM_VOY' => strtolower(trim($this->voyageCode)),
            'pageNumber' => 'undefined',
        ];
        $fullUrl = $url . '?' . http_build_query($queryParams);

        $response = Http::withHeaders([
            'Host' => 'terminaltracking.ksp.kln.com',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Cache-Control' => 'max-age=0',
            'Cookie' => 'UserToken=00000111112222233333',
        ])
        ->timeout(30)
        ->post($fullUrl);

        if (!$response->successful()) {
            Log::warning("Kerry API request failed", [
                'status' => $response->status(),
                'vessel' => $this->vesselName,
            ]);
            return null;
        }

        return $response->body();
    }

    protected function parseKerryHtml(string $html): array
    {
        $results = [];

        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rows);

        foreach ($rows[1] as $rowContent) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowContent, $cells);

            if (count($cells[1]) < 11) {
                continue;
            }

            $cellData = array_map(function ($cell) {
                return trim(strip_tags($cell));
            }, $cells[1]);

            // Skip empty/header rows
            if (empty($cellData[1]) || empty($cellData[5])) {
                continue;
            }

            $results[] = [
                'vessel_code' => $cellData[0],
                'vessel_name' => $cellData[1],
                'voyage_code' => $cellData[2],  // I/B Voyage
                'ob_voyage' => $cellData[3],     // O/B Voyage
                'phase' => $cellData[4],
                'eta' => $cellData[5],
                'etd' => $cellData[6],
                'ata' => $cellData[7],
                'atd' => $cellData[8],
                'open_gate' => $cellData[9],
                'closing_time' => $cellData[10],
            ];
        }

        return $results;
    }

    protected function parseKerryDate(?string $dateStr): ?\Carbon\Carbon
    {
        if (empty($dateStr) || $dateStr === '&nbsp;' || $dateStr === '-') {
            return null;
        }

        // Format: "DD/MM HH:MM" e.g. "27/02 02:00"
        if (!preg_match('/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}:\d{2})/', $dateStr, $matches)) {
            return null;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $time = $matches[3];
        $year = (int) date('Y');

        try {
            $date = \Carbon\Carbon::createFromFormat('Y-m-d H:i', "{$year}-{$month}-{$day} {$time}");

            // 6-month lookback: if date is more than 6 months in the future, it's probably last year
            if ($date->gt(now()->addMonths(6))) {
                $date->subYear();
            }

            return $date;
        } catch (\Exception $e) {
            Log::warning("Failed to parse Kerry date: {$dateStr}");
            return null;
        }
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
            'terminal' => 'kerry',
            'ports_scraped' => [$this->portTerminal],
            'vessels_found' => $vesselsFound,
            'schedules_created' => $created,
            'schedules_updated' => $updated,
            'status' => $status,
            'error_message' => $error,
            'duration_seconds' => (int) $duration,
        ]);
    }
}
