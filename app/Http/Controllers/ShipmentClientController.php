<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentClient;
use App\Models\EtaCheckLog;
use App\Services\LineMessagingService;
use App\Services\VesselTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class ShipmentClientController extends Controller
{
    public function generateClientLink(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email',
            'client_phone' => 'nullable|string|max:20',
        ]);

        if (!Auth::check()) {
            return response()->json(['error' => 'Please login first'], 401);
        }

        // Allow all authenticated users to generate client links

        $shipment = Shipment::findOrFail($request->shipment_id);

        $shipmentClient = ShipmentClient::create([
            'shipment_id' => $shipment->id,
            'client_name' => $request->client_name,
            'client_email' => $request->client_email,
            'client_phone' => $request->client_phone,
            'verification_token' => ShipmentClient::generateVerificationToken(),
            'expires_at' => now()->addDays(30), // Valid for 30 days
            'is_active' => true,
        ]);

        Log::info('Client LINE link generated', [
            'shipment_id' => $shipment->id,
            'client_name' => $request->client_name,
            'verification_token' => $shipmentClient->verification_token,
            'generated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'login_url' => $shipmentClient->getLoginUrl(),
            'client_id' => $shipmentClient->id,
        ]);
    }

    public function redirectToLineLogin($token)
    {
        $shipmentClient = ShipmentClient::where('verification_token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        try {
            // Store the token in session for callback
            session(['client_line_token' => $token]);

            // Use client-specific LINE configuration
            return Socialite::buildProvider(
                \Revolution\Line\Socialite\LineLoginProvider::class,
                config('services.line-client')
            )->redirect();
        } catch (\Exception $e) {
            Log::error('Client LINE Login redirect error: ' . $e->getMessage());
            return view('client.line-error', [
                'error' => 'Failed to connect to LINE. Please try again.',
                'shipment' => $shipmentClient->shipment
            ]);
        }
    }

    public function handleLineCallback(Request $request)
    {
        try {
            // Get the token from session
            $token = session('client_line_token');
            if (!$token) {
                throw new \Exception('No client token found in session');
            }

            $shipmentClient = ShipmentClient::where('verification_token', $token)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->firstOrFail();

            // Use client-specific LINE configuration
            $lineUser = Socialite::buildProvider(
                \Revolution\Line\Socialite\LineLoginProvider::class,
                config('services.line-client')
            )->user();

            // Allow LINE account to be connected to multiple shipments
            // Check if this specific shipment already has this LINE account connected
            $existingConnectionForShipment = ShipmentClient::where('line_user_id', $lineUser->getId())
                ->where('shipment_id', $shipmentClient->shipment_id)
                ->where('id', '!=', $shipmentClient->id)
                ->first();

            if ($existingConnectionForShipment) {
                return view('client.line-error', [
                    'error' => 'This LINE account is already connected to this specific shipment.',
                    'shipment' => $shipmentClient->shipment
                ]);
            }

            // Update shipment client with LINE information
            $shipmentClient->update([
                'line_user_id' => $lineUser->getId(),
                'line_display_name' => $lineUser->getName(),
                'line_picture_url' => $lineUser->getAvatar(),
                'line_connected_at' => now(),
            ]);

            Log::info('Client LINE account connected successfully', [
                'shipment_client_id' => $shipmentClient->id,
                'shipment_id' => $shipmentClient->shipment_id,
                'line_user_id' => $lineUser->getId(),
                'line_display_name' => $lineUser->getName()
            ]);

            // Send welcome message via LINE
            try {
                $lineMessaging = new LineMessagingService();
                $lineMessaging->sendClientWelcomeMessage($shipmentClient);
            } catch (\Exception $e) {
                Log::warning('Failed to send client LINE welcome message: ' . $e->getMessage());
            }

            return view('client.line-success', [
                'shipment' => $shipmentClient->shipment,
                'client' => $shipmentClient
            ]);

        } catch (\Exception $e) {
            Log::error('Client LINE Login callback error: ' . $e->getMessage());
            return view('client.line-error', [
                'error' => 'Failed to connect LINE account. Please try again.',
                'shipment' => null
            ]);
        }
    }

    public function sendTestNotification(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
        ]);

        if (!Auth::check()) {
            return response()->json(['error' => 'Please login first'], 401);
        }

        // Allow all authenticated users to send notifications

        $shipment = Shipment::findOrFail($request->shipment_id);
        $connectedClients = $shipment->activeShipmentClients()->whereNotNull('line_user_id')->get();

        if ($connectedClients->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No connected LINE clients found for this shipment.'
            ]);
        }

        $successCount = 0;
        $lineMessaging = new LineMessagingService();

        foreach ($connectedClients as $client) {
            try {
                $lineMessaging->sendShipmentEtaUpdate($client);
                $successCount++;
            } catch (\Exception $e) {
                Log::error('Failed to send ETA notification to client', [
                    'client_id' => $client->id,
                    'line_user_id' => $client->line_user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Test ETA notifications sent', [
            'shipment_id' => $shipment->id,
            'total_clients' => $connectedClients->count(),
            'successful_sends' => $successCount,
            'sent_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Test notifications sent to {$successCount} out of {$connectedClients->count()} connected clients."
        ]);
    }

    public function checkShipmentETA(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
        ]);

        if (!Auth::check()) {
            return response()->json(['error' => 'Please login first'], 401);
        }

        // Allow all authenticated users to check ETA

        $shipment = Shipment::with('vessel')->findOrFail($request->shipment_id);

        // Check if we have required data for vessel tracking
        if (!$shipment->vessel && !$shipment->vessel_code) {
            return response()->json([
                'success' => false,
                'error' => 'No vessel information available for this shipment'
            ]);
        }

        if (!$shipment->port_terminal) {
            return response()->json([
                'success' => false,
                'error' => 'No port terminal specified for this shipment'
            ]);
        }

        try {
            // Build vessel name for tracking
            $vesselName = $shipment->vessel ? $shipment->vessel->name : $shipment->vessel_code;
            $vesselFullName = $vesselName . ($shipment->voyage ? ' ' . $shipment->voyage : '');

            Log::info('Starting ETA check for shipment', [
                'shipment_id' => $shipment->id,
                'vessel_name' => $vesselName,
                'voyage' => $shipment->voyage,
                'port_terminal' => $shipment->port_terminal,
                'initiated_by' => Auth::id()
            ]);

            // Update the check date first
            $currentTime = now();
            $shipment->update([
                'last_eta_check_date' => $currentTime
            ]);

            Log::info('Updated last_eta_check_date', [
                'shipment_id' => $shipment->id,
                'timestamp' => $currentTime->format('Y-m-d H:i:s')
            ]);

            $vesselTrackingService = new VesselTrackingService();

            // Check ETA using the shipment's port terminal (now uses VesselTrackingService codes directly)
            $result = $vesselTrackingService->checkVesselETAByName($vesselFullName, $shipment->port_terminal);

            // Initialize log data
            $logData = [
                'shipment_id' => $shipment->id,
                'terminal' => $shipment->port_terminal,
                'vessel_name' => $vesselName,
                'voyage_code' => $shipment->voyage,
                'shipment_eta_at_time' => $shipment->planned_delivery_date,
                'initiated_by' => Auth::id(),
            ];

            if ($result && $result['success']) {
                // Initialize update data
                $updateData = [];

                // Store bot_received_eta_date only (do not update original planned_delivery_date)
                // Handle both old format (vessel_found) and new browser automation format (success)
                $vesselFound = isset($result['vessel_found']) ? $result['vessel_found'] : $result['success'];
                if ($vesselFound && isset($result['eta']) && $result['eta']) {
                    try {
                        // Try to parse ETA with different formats
                        $etaString = $result['eta'];
                        $etaDate = null;

                        // First try Carbon::parse (handles most standard formats)
                        try {
                            $etaDate = \Carbon\Carbon::parse($etaString);
                        } catch (\Exception $e) {
                            // If parse fails, try specific formats
                            $formats = [
                                'd/m/Y',           // 23/09/2025 (DD/MM/YYYY format from TIPS) - try this FIRST
                                'Y-m-d',           // 2025-09-23
                                'm/d/Y',           // 09/23/2025 (US format)
                                'Y/m/d',           // 2025/09/23
                                'd-m-Y',           // 23-09-2025
                                'Y-m-d H:i:s',     // 2025-09-23 08:00:00
                                'd/m/Y H:i',       // 23/09/2025 08:00
                            ];

                            foreach ($formats as $format) {
                                try {
                                    $etaDate = \Carbon\Carbon::createFromFormat($format, $etaString);
                                    break;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                        }

                        if ($etaDate) {
                            // Only store the bot ETA, do not overwrite original planned_delivery_date
                            $updateData['bot_received_eta_date'] = $etaDate; // Store the actual ETA from port website
                            Log::info('Stored bot ETA without updating original planned delivery date', [
                                'shipment_id' => $shipment->id,
                                'original_planned_eta' => $shipment->planned_delivery_date ? $shipment->planned_delivery_date->format('Y-m-d H:i:s') : 'null',
                                'bot_scraped_eta' => $etaDate->format('Y-m-d H:i:s'),
                                'port_eta' => $result['eta']
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to parse ETA date', [
                            'shipment_id' => $shipment->id,
                            'eta' => $result['eta'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Determine tracking status based on comparison with shipment's planned ETA
                // Both vessel AND voyage must be found to proceed with comparison
                $voyageFound = isset($result['voyage_found']) ? $result['voyage_found'] : false;
                if ($vesselFound && $voyageFound && isset($result['eta']) && $result['eta']) {
                    // Compare scraped ETA with shipment's planned delivery date
                    try {
                        // Use the same ETA parsing logic as above
                        $etaString = $result['eta'];
                        $scrapedEta = null;

                        // First try Carbon::parse (handles most standard formats)
                        try {
                            $scrapedEta = \Carbon\Carbon::parse($etaString);
                        } catch (\Exception $e) {
                            // If parse fails, try specific formats
                            $formats = [
                                'd/m/Y',           // 23/09/2025 (DD/MM/YYYY format from TIPS) - try this FIRST
                                'Y-m-d',           // 2025-09-23
                                'm/d/Y',           // 09/23/2025 (US format)
                                'Y/m/d',           // 2025/09/23
                                'd-m-Y',           // 23-09-2025
                                'Y-m-d H:i:s',     // 2025-09-23 08:00:00
                                'd/m/Y H:i',       // 23/09/2025 08:00
                            ];

                            foreach ($formats as $format) {
                                try {
                                    $scrapedEta = \Carbon\Carbon::createFromFormat($format, $etaString);
                                    break;
                                } catch (\Exception $e) {
                                    continue;
                                }
                            }
                        }

                        if ($scrapedEta) {
                            $shipmentEta = $shipment->planned_delivery_date;

                            if ($shipmentEta) {
                                // Compare dates with tolerance of 1 day for "on_track"
                                $daysDifference = $scrapedEta->diffInDays($shipmentEta, false);

                                if ($scrapedEta->lt($shipmentEta)) {
                                    // Scraped ETA is earlier than planned
                                    $updateData['tracking_status'] = 'early';
                                } elseif ($scrapedEta->equalTo($shipmentEta) || $daysDifference <= 1) {
                                    // Same day or within 1 day tolerance
                                    $updateData['tracking_status'] = 'on_track';
                                } else {
                                    // Scraped ETA is later than planned
                                    $updateData['tracking_status'] = 'delay';
                                }
                            } else {
                                // No shipment ETA to compare with, consider on track if vessel found
                                $updateData['tracking_status'] = 'on_track';
                            }

                            Log::info('ETA comparison completed', [
                                'shipment_id' => $shipment->id,
                                'scraped_eta' => $scrapedEta->format('Y-m-d H:i:s'),
                                'shipment_eta' => $shipmentEta ? $shipmentEta->format('Y-m-d H:i:s') : 'not_set',
                                'tracking_status' => $updateData['tracking_status']
                            ]);
                        } else {
                            // Could not parse ETA, default to on_track
                            $updateData['tracking_status'] = 'on_track';
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to compare ETA dates', [
                            'shipment_id' => $shipment->id,
                            'scraped_eta' => $result['eta'],
                            'error' => $e->getMessage()
                        ]);
                        $updateData['tracking_status'] = 'on_track'; // Default to on_track if comparison fails
                    }
                } else {
                    // Either vessel or voyage (or both) not found
                    // Check if vessel was previously found (departed detection)
                    $lastSuccessfulCheck = EtaCheckLog::where('shipment_id', $shipment->id)
                        ->where('vessel_found', true)
                        ->where('voyage_found', true)
                        ->whereNotNull('updated_eta')
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($lastSuccessfulCheck) {
                        // Vessel was found before but not now - likely departed
                        $updateData['tracking_status'] = 'departed';

                        // Keep the last known ETA and status
                        if (!isset($updateData['bot_received_eta_date']) && $lastSuccessfulCheck->updated_eta) {
                            $updateData['bot_received_eta_date'] = $lastSuccessfulCheck->updated_eta;
                        }

                        Log::info('Vessel departed - was found previously but not now', [
                            'shipment_id' => $shipment->id,
                            'last_found_at' => $lastSuccessfulCheck->created_at,
                            'last_eta' => $lastSuccessfulCheck->updated_eta
                        ]);
                    } else {
                        // Never found before
                        $updateData['tracking_status'] = 'not_found';
                    }
                }

                $shipment->update($updateData);

                // Log the ETA check result
                $logData = array_merge($logData, [
                    'updated_eta' => isset($updateData['bot_received_eta_date']) ? $updateData['bot_received_eta_date'] : null,
                    'tracking_status' => $updateData['tracking_status'],
                    'vessel_found' => $vesselFound,
                    'voyage_found' => $result['voyage_found'] ?? false,
                    'raw_response' => $result,
                ]);

                EtaCheckLog::create($logData);

                Log::info('ETA check completed successfully', [
                    'shipment_id' => $shipment->id,
                    'vessel_found' => $vesselFound,
                    'eta_found' => isset($result['eta']) && $result['eta'],
                    'tracking_status' => $updateData['tracking_status'],
                    'port_eta' => $result['eta'] ?? null,
                    'bot_received_eta_date_stored' => isset($updateData['bot_received_eta_date']) ? $updateData['bot_received_eta_date']->format('Y-m-d H:i:s') : 'not_updated'
                ]);

                return response()->json([
                    'success' => true,
                    'vessel_found' => $vesselFound,
                    'voyage_found' => $result['voyage_found'] ?? false,
                    'eta' => $result['eta'] ?? null,
                    'tracking_status' => $updateData['tracking_status'],
                    'terminal' => $result['terminal'] ?? $shipment->port_terminal,
                    'message' => $vesselFound
                        ? 'Vessel tracking completed successfully'
                        : 'Vessel not found in current schedule'
                ]);
            } else {
                // Update tracking status to not_found if check failed
                $shipment->update([
                    'tracking_status' => 'not_found'
                ]);

                $errorMessage = $result['error'] ?? 'Unknown error during vessel tracking';

                // Log the failed ETA check
                $logData = array_merge($logData, [
                    'tracking_status' => 'not_found',
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'error_message' => $errorMessage,
                    'raw_response' => $result,
                ]);

                EtaCheckLog::create($logData);

                Log::warning('ETA check failed', [
                    'shipment_id' => $shipment->id,
                    'error' => $errorMessage
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                    'tracking_status' => 'not_found'
                ]);
            }

        } catch (\Exception $e) {
            // Update tracking status to not_found on exception
            $shipment->update([
                'tracking_status' => 'not_found'
            ]);

            // Log the exception
            if (isset($logData)) {
                $logData = array_merge($logData, [
                    'tracking_status' => 'not_found',
                    'vessel_found' => false,
                    'voyage_found' => false,
                    'error_message' => 'Exception: ' . $e->getMessage(),
                ]);

                EtaCheckLog::create($logData);
            }

            Log::error('ETA check exception', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to check vessel ETA: ' . $e->getMessage(),
                'tracking_status' => 'not_found'
            ]);
        }
    }
}
