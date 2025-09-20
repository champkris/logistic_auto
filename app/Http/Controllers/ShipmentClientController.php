<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentClient;
use App\Services\LineMessagingService;
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

            // Check if this LINE account is already connected to another shipment client
            $existingClient = ShipmentClient::where('line_user_id', $lineUser->getId())
                ->where('id', '!=', $shipmentClient->id)
                ->first();

            if ($existingClient) {
                return view('client.line-error', [
                    'error' => 'This LINE account is already connected to another shipment.',
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
}
