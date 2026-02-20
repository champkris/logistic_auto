<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiamgpsService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.siamgps.base_url', 'https://services.siamgpstrack.com'), '/');
        $this->token = config('services.siamgps.token', '');
    }

    /**
     * Get all vehicles. Cached for 1 hour.
     */
    public function getVehicles(): array
    {
        return Cache::remember('siamgps_vehicles', 3600, function () {
            try {
                $response = Http::withToken($this->token)
                    ->timeout(15)
                    ->get("{$this->baseUrl}/vehicles");

                if (!$response->successful()) {
                    Log::error('SiamgpsService: Failed to fetch vehicles', ['status' => $response->status()]);
                    return [];
                }

                return $response->json('data', []);
            } catch (\Exception $e) {
                Log::error('SiamgpsService: Exception fetching vehicles', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Find a vehicle by license plate. Normalized comparison (strip spaces, dashes, dots).
     */
    public function findVehicleByPlate(string $plate): ?array
    {
        $vehicles = $this->getVehicles();
        $normalizedSearch = $this->normalizePlate($plate);

        if (empty($normalizedSearch)) {
            return null;
        }

        // Exact normalized match first
        foreach ($vehicles as $vehicle) {
            $vehiclePlate = $this->normalizePlate($vehicle['_vehiPlateNo'] ?? '');
            if ($vehiclePlate === $normalizedSearch) {
                return $vehicle;
            }
        }

        // Partial match fallback (search plate contained in vehicle plate or vice versa)
        foreach ($vehicles as $vehicle) {
            $vehiclePlate = $this->normalizePlate($vehicle['_vehiPlateNo'] ?? '');
            if (!empty($vehiclePlate) && (
                str_contains($vehiclePlate, $normalizedSearch) ||
                str_contains($normalizedSearch, $vehiclePlate)
            )) {
                return $vehicle;
            }
        }

        return null;
    }

    /**
     * Get realtime data for a single vehicle by ID.
     * Note: API has typo "localtion" in single vehicle endpoint.
     */
    public function getRealtimeByVehicleId(string $id): ?array
    {
        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->get("{$this->baseUrl}/realtime/listByVehicleId/{$id}");

            if (!$response->successful()) {
                Log::warning('SiamgpsService: Failed to fetch realtime for vehicle', [
                    'vehicle_id' => $id,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $json = $response->json();
            $vehicleInfo = $json['vehicleInfo'] ?? [];
            $data = $json['data'][0] ?? null;

            if (!$data) {
                return null;
            }

            // Handle API typo: "localtion" vs "location"
            $coordinates = $data['localtion']['coordinates']
                ?? $data['location']['coordinates']
                ?? null;

            return [
                'vehicleInfo' => $vehicleInfo,
                'coordinates' => $coordinates, // [lng, lat]
                'speed' => $data['speed'] ?? 0,
                'vehicleStatus' => $data['vehicleStatus'] ?? 'UNKNOWN',
                'time' => $data['time'] ?? null,
                'geoLocation' => $data['geoLocation']['sGeo'] ?? '',
                'heading' => $data['heading'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('SiamgpsService: Exception fetching realtime', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get realtime data for all vehicles (bulk endpoint).
     */
    public function getAllRealtime(): array
    {
        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->get("{$this->baseUrl}/realtime/list");

            if (!$response->successful()) {
                Log::error('SiamgpsService: Failed to fetch bulk realtime', ['status' => $response->status()]);
                return [];
            }

            return $response->json('data', []);
        } catch (\Exception $e) {
            Log::error('SiamgpsService: Exception fetching bulk realtime', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Resolve multiple license plates to GPS locations.
     * Uses bulk API for 3+ plates, individual for fewer.
     *
     * Returns array keyed by original plate:
     * [
     *   'plate' => '...',
     *   'found' => true/false,
     *   'vehicleId' => ...,
     *   'vehicleName' => ...,
     *   'lat' => ...,
     *   'lng' => ...,
     *   'speed' => ...,
     *   'vehicleStatus' => ...,
     *   'geoLocation' => ...,
     *   'time' => ...,
     * ]
     */
    public function resolveMultiplePlates(array $plates): array
    {
        $plates = array_filter(array_map('trim', $plates));
        $plates = array_unique($plates);

        if (empty($plates)) {
            return [];
        }

        if (count($plates) >= 3) {
            return $this->resolveViaBulk($plates);
        }

        return $this->resolveViaIndividual($plates);
    }

    /**
     * Resolve plates using the bulk /realtime/list endpoint.
     */
    protected function resolveViaBulk(array $plates): array
    {
        $allRealtime = $this->getAllRealtime();
        $results = [];

        foreach ($plates as $plate) {
            $normalizedSearch = $this->normalizePlate($plate);
            $matched = null;

            foreach ($allRealtime as $item) {
                $vehiclePlate = $this->normalizePlate($item['vehicleInfo']['_vehiPlateNo'] ?? '');
                if ($vehiclePlate === $normalizedSearch ||
                    (!empty($vehiclePlate) && (
                        str_contains($vehiclePlate, $normalizedSearch) ||
                        str_contains($normalizedSearch, $vehiclePlate)
                    ))
                ) {
                    $matched = $item;
                    break;
                }
            }

            if ($matched) {
                $coordinates = $matched['location']['coordinates'] ?? null;
                $results[] = [
                    'plate' => $plate,
                    'found' => true,
                    'vehicleId' => $matched['vehicleInfo']['_id'] ?? null,
                    'vehicleName' => $matched['vehicleInfo']['_name'] ?? '',
                    'vehiclePlate' => $matched['vehicleInfo']['_vehiPlateNo'] ?? $plate,
                    'lat' => $coordinates[1] ?? null,
                    'lng' => $coordinates[0] ?? null,
                    'speed' => $matched['speed'] ?? 0,
                    'vehicleStatus' => $matched['vehicleStatus'] ?? 'UNKNOWN',
                    'geoLocation' => $matched['geoLocation']['sGeo'] ?? '',
                    'time' => $matched['time'] ?? null,
                    'heading' => $matched['heading'] ?? 0,
                ];
            } else {
                $results[] = [
                    'plate' => $plate,
                    'found' => false,
                    'vehicleId' => null,
                    'vehicleName' => '',
                    'vehiclePlate' => $plate,
                    'lat' => null,
                    'lng' => null,
                    'speed' => 0,
                    'vehicleStatus' => 'NOT_FOUND',
                    'geoLocation' => '',
                    'time' => null,
                    'heading' => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * Resolve plates individually using findVehicleByPlate + getRealtimeByVehicleId.
     */
    protected function resolveViaIndividual(array $plates): array
    {
        $results = [];

        foreach ($plates as $plate) {
            $vehicle = $this->findVehicleByPlate($plate);

            if (!$vehicle) {
                $results[] = [
                    'plate' => $plate,
                    'found' => false,
                    'vehicleId' => null,
                    'vehicleName' => '',
                    'vehiclePlate' => $plate,
                    'lat' => null,
                    'lng' => null,
                    'speed' => 0,
                    'vehicleStatus' => 'NOT_FOUND',
                    'geoLocation' => '',
                    'time' => null,
                    'heading' => 0,
                ];
                continue;
            }

            $realtime = $this->getRealtimeByVehicleId((string) $vehicle['_id']);

            if ($realtime && $realtime['coordinates']) {
                $results[] = [
                    'plate' => $plate,
                    'found' => true,
                    'vehicleId' => $vehicle['_id'],
                    'vehicleName' => $vehicle['_name'] ?? '',
                    'vehiclePlate' => $vehicle['_vehiPlateNo'] ?? $plate,
                    'lat' => $realtime['coordinates'][1] ?? null,
                    'lng' => $realtime['coordinates'][0] ?? null,
                    'speed' => $realtime['speed'],
                    'vehicleStatus' => $realtime['vehicleStatus'],
                    'geoLocation' => $realtime['geoLocation'],
                    'time' => $realtime['time'],
                    'heading' => $realtime['heading'],
                ];
            } else {
                $results[] = [
                    'plate' => $plate,
                    'found' => false,
                    'vehicleId' => $vehicle['_id'],
                    'vehicleName' => $vehicle['_name'] ?? '',
                    'vehiclePlate' => $vehicle['_vehiPlateNo'] ?? $plate,
                    'lat' => null,
                    'lng' => null,
                    'speed' => 0,
                    'vehicleStatus' => 'NO_GPS',
                    'geoLocation' => '',
                    'time' => null,
                    'heading' => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * Normalize a license plate for comparison.
     * Strips spaces, dashes, dots, and lowercases.
     */
    protected function normalizePlate(string $plate): string
    {
        return mb_strtolower(preg_replace('/[\s\-\.]+/', '', trim($plate)));
    }
}
