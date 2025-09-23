<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentClient;
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

        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

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

        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

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

        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

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

            if ($result && $result['success']) {
                // Initialize update data
                $updateData = [];

                // Update the planned delivery date and bot_received_eta_date if ETA found
                // Handle both old format (vessel_found) and new browser automation format (success)
                $vesselFound = isset($result['vessel_found']) ? $result['vessel_found'] : $result['success'];
                if ($vesselFound && isset($result['eta']) && $result['eta']) {
                    try {
                        $etaDate = \Carbon\Carbon::parse($result['eta']);
                        $updateData['planned_delivery_date'] = $etaDate;
                        $updateData['bot_received_eta_date'] = $etaDate; // Store the actual ETA from port website
                        Log::info('Updated planned_delivery_date from ETA check', [
                            'shipment_id' => $shipment->id,
                            'old_eta' => $shipment->planned_delivery_date ? $shipment->planned_delivery_date->format('Y-m-d H:i:s') : 'null',
                            'new_eta' => $etaDate->format('Y-m-d H:i:s'),
                            'port_eta' => $result['eta']
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to parse ETA date', [
                            'shipment_id' => $shipment->id,
                            'eta' => $result['eta'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Determine tracking status based on results
                if ($vesselFound && isset($result['eta']) && $result['eta']) {
                    $updateData['tracking_status'] = 'on_track';
                } else {
                    $updateData['tracking_status'] = 'delay';
                }

                $shipment->update($updateData);

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
                // Update tracking status to delay if check failed
                $shipment->update([
                    'tracking_status' => 'delay'
                ]);

                $errorMessage = $result['error'] ?? 'Unknown error during vessel tracking';

                Log::warning('ETA check failed', [
                    'shipment_id' => $shipment->id,
                    'error' => $errorMessage
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                    'tracking_status' => 'delay'
                ]);
            }

        } catch (\Exception $e) {
            // Update tracking status to delay on exception
            $shipment->update([
                'tracking_status' => 'delay'
            ]);

            Log::error('ETA check exception', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to check vessel ETA: ' . $e->getMessage(),
                'tracking_status' => 'delay'
            ]);
        }
    }
}
