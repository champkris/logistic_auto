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
use Illuminate\Support\Facades\Log;

class ScrapeLCB1Vessel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = 30;

    public function __construct(
        public string $vesselName,
        public string $voyageCode,
        public string $portTerminal = 'A0'
    ) {}

    public function middleware(): array
    {
        return [new RateLimited('lcb1-api')];
    }

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            $result = $this->callScraper();

            if ($result === null) {
                $this->logScrape('failed', 0, 0, 0, microtime(true) - $startTime, 'Scraper returned no output');
                return;
            }

            if (!$result['success']) {
                $this->logScrape('failed', 0, 0, 0, microtime(true) - $startTime, $result['error'] ?? 'Unknown error');
                return;
            }

            if (!($result['vessel_found'] ?? false)) {
                Log::info("LCB1 scrape: vessel not found for {$this->vesselName}/{$this->voyageCode}");
                $this->logScrape('success', 0, 0, 0, microtime(true) - $startTime);
                return;
            }

            $eta = $result['eta'] ?? null;

            if (!$eta) {
                $this->logScrape('success', 0, 0, 0, microtime(true) - $startTime);
                return;
            }

            // Skip if ETA is more than 1 month in the past
            $etaCarbon = \Carbon\Carbon::parse($eta);
            if ($etaCarbon->lt(now()->subMonth())) {
                $this->logScrape('success', 1, 0, 0, microtime(true) - $startTime);
                return;
            }

            $schedule = VesselSchedule::updateOrCreate(
                [
                    'vessel_name' => strtoupper(trim($result['vessel_name'] ?? $this->vesselName)),
                    'port_terminal' => $this->portTerminal,
                    'voyage_code' => $result['voyage_code'] ?? $this->voyageCode,
                ],
                [
                    'berth' => $result['berth'] ?? null,
                    'eta' => $etaCarbon,
                    'etd' => !empty($result['etd']) ? \Carbon\Carbon::parse($result['etd']) : null,
                    'source' => 'lcb1',
                    'raw_data' => $result['raw_data'] ?? null,
                    'scraped_at' => now(),
                    'expires_at' => now()->addHours(48),
                ]
            );

            $created = $schedule->wasRecentlyCreated ? 1 : 0;
            $updated = $schedule->wasRecentlyCreated ? 0 : 1;

            Log::info("LCB1 scrape completed for {$this->vesselName}", [
                'vessel' => $this->vesselName,
                'voyage' => $this->voyageCode,
                'created' => $created,
                'updated' => $updated,
            ]);

            $this->logScrape('success', 1, $created, $updated, microtime(true) - $startTime);

        } catch (\Exception $e) {
            Log::error("LCB1 scrape failed for {$this->vesselName}: " . $e->getMessage());
            $this->logScrape('failed', 0, 0, 0, microtime(true) - $startTime, $e->getMessage());
            throw $e;
        }
    }

    protected function callScraper(): ?array
    {
        $command = sprintf(
            'cd %s && timeout 30s node scrapers/lcb1-full-schedule-scraper.js --vessel %s --voyage %s',
            escapeshellarg(base_path('browser-automation')),
            escapeshellarg($this->vesselName),
            escapeshellarg($this->voyageCode ?: '')
        );

        $descriptors = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);

        $jsonOutput = stream_get_contents($pipes[1]);
        $logOutput = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        if (!empty($logOutput)) {
            Log::info("LCB1 scraper logs for {$this->vesselName}:", ['logs' => $logOutput]);
        }

        if (!$jsonOutput) {
            return null;
        }

        return json_decode(trim($jsonOutput), true);
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
            'terminal' => 'lcb1',
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
