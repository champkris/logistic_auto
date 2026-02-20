<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoogleSheetService
{
    protected string $sheetUrl;

    public function __construct()
    {
        $this->sheetUrl = config('services.google_sheets.transport_sheet_url');
    }

    /**
     * Fetch and parse all transport data from Google Sheet CSV.
     * Cached for 5 minutes.
     */
    public function getTransportData(bool $forceRefresh = false): array
    {
        $cacheKey = 'transport_sheet_data';

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 300, function () {
            return $this->fetchAndParseCsv();
        });
    }

    /**
     * Search unique customer names matching the query.
     */
    public function searchCustomers(string $search): array
    {
        if (strlen($search) < 2) {
            return [];
        }

        $data = $this->getTransportData();
        $searchLower = mb_strtolower(trim($search));

        $customers = collect($data)
            ->pluck('CUSTOMER')
            ->filter(fn($name) => !empty(trim($name)))
            ->unique()
            ->filter(fn($name) => str_contains(mb_strtolower($name), $searchLower))
            ->sort()
            ->values()
            ->take(20)
            ->toArray();

        return $customers;
    }

    /**
     * Search by BL number. Returns all matching rows (across all dates/customers).
     */
    public function searchByBl(string $search): array
    {
        if (strlen(trim($search)) < 2) {
            return [];
        }

        $data = $this->getTransportData();
        $searchLower = mb_strtolower(trim($search));

        return collect($data)
            ->filter(fn($row) => str_contains(mb_strtolower($row['BL'] ?? ''), $searchLower))
            ->values()
            ->toArray();
    }

    /**
     * Get rows for a specific customer and date.
     * Date should be in d/m/Y format to match sheet data.
     */
    public function getRowsForCustomer(string $customer, string $date): array
    {
        $data = $this->getTransportData();
        $customerLower = mb_strtolower(trim($customer));

        // Parse the target date
        $targetDate = $this->parseSheetDate($date);
        if (!$targetDate) {
            return [];
        }

        return collect($data)
            ->filter(function ($row) use ($customerLower, $targetDate) {
                $rowCustomer = mb_strtolower(trim($row['CUSTOMER'] ?? ''));
                if ($rowCustomer !== $customerLower) {
                    return false;
                }

                $rowDate = $this->parseSheetDate($row['DELIVERY DATE'] ?? $row['LOAD DATE'] ?? '');
                if (!$rowDate) {
                    return false;
                }

                return $rowDate->isSameDay($targetDate);
            })
            ->values()
            ->toArray();
    }

    /**
     * Get all dates that have data for a specific customer.
     * Returns sorted array of dates in d/m/Y format.
     */
    public function getAvailableDatesForCustomer(string $customer): array
    {
        $data = $this->getTransportData();
        $customerLower = mb_strtolower(trim($customer));

        return collect($data)
            ->filter(fn($row) => mb_strtolower(trim($row['CUSTOMER'] ?? '')) === $customerLower)
            ->map(fn($row) => $row['DELIVERY DATE'] ?? $row['LOAD DATE'] ?? '')
            ->filter(fn($date) => !empty(trim($date)))
            ->map(function ($date) {
                $parsed = $this->parseSheetDate($date);
                return $parsed ? $parsed->format('d/m/Y') : null;
            })
            ->filter()
            ->unique()
            ->sort(function ($a, $b) {
                $dateA = Carbon::createFromFormat('d/m/Y', $a);
                $dateB = Carbon::createFromFormat('d/m/Y', $b);
                return $dateA->timestamp - $dateB->timestamp;
            })
            ->values()
            ->toArray();
    }

    /**
     * Parse sheet date format (d/m/Y or j/n/Y) into Carbon.
     */
    protected function parseSheetDate(string $dateStr): ?Carbon
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }

        // Try various formats the sheet might use
        $formats = ['j/n/Y', 'd/m/Y', 'j/n/y', 'd/m/y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $dateStr)->startOfDay();
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Fetch CSV from Google Sheets and parse into array of rows.
     * Row 1 = category headers (skip)
     * Row 2 = actual column names
     * Row 3+ = data
     */
    protected function fetchAndParseCsv(): array
    {
        try {
            $response = Http::timeout(30)->get($this->sheetUrl);

            if (!$response->successful()) {
                Log::error('GoogleSheetService: Failed to fetch CSV', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            $csv = $response->body();
            $lines = explode("\n", $csv);

            if (count($lines) < 3) {
                Log::warning('GoogleSheetService: CSV has fewer than 3 rows');
                return [];
            }

            // Row 1 (index 0) = category headers - skip
            // Row 2 (index 1) = actual column names
            $headers = str_getcsv($lines[1], ',', '"', '');
            $headers = array_map('trim', $headers);

            $rows = [];
            for ($i = 2; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) {
                    continue;
                }

                $values = str_getcsv($line, ',', '"', '');
                $row = [];

                foreach ($headers as $idx => $header) {
                    if (empty($header)) {
                        continue;
                    }
                    $row[$header] = trim($values[$idx] ?? '');
                }

                // Skip rows with no meaningful data
                if (empty($row['CUSTOMER'] ?? '') && empty($row['CONTAINER NO'] ?? '')) {
                    continue;
                }

                $rows[] = $row;
            }

            Log::info('GoogleSheetService: Parsed CSV', ['row_count' => count($rows)]);
            return $rows;

        } catch (\Exception $e) {
            Log::error('GoogleSheetService: Exception fetching CSV', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
