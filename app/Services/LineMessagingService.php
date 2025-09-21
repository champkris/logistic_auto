<?php

namespace App\Services;

use App\Models\User;
use App\Models\ShipmentClient;
use Illuminate\Support\Facades\Log;
use Revolution\Line\Facades\Bot;
use LINE\Clients\MessagingApi\Model\PushMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;

class LineMessagingService
{
    public function __construct()
    {
        // LINE Bot is handled via Facade
    }

    /**
     * Send a text message to a specific LINE user
     */
    public function sendTextMessage(string $lineUserId, string $message): bool
    {
        try {
            // Create text message object
            $textMessage = new TextMessage([
                'type' => 'text',
                'text' => $message
            ]);

            // Create push message request
            $pushMessageRequest = new PushMessageRequest([
                'to' => $lineUserId,
                'messages' => [$textMessage]
            ]);

            // Send the message
            $response = Bot::pushMessage($pushMessageRequest);

            Log::info('LINE message sent successfully', [
                'line_user_id' => $lineUserId,
                'message' => $message
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('LINE messaging error: ' . $e->getMessage(), [
                'line_user_id' => $lineUserId,
                'message' => $message,
                'error_trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send shipment notification to user
     */
    public function sendShipmentNotification(User $user, string $type, array $data): bool
    {
        if (!$user->hasLineAccount()) {
            return false;
        }

        $message = $this->buildShipmentMessage($type, $data);
        return $this->sendTextMessage($user->line_user_id, $message);
    }

    /**
     * Send vessel arrival notification
     */
    public function sendVesselArrivalNotification(User $user, array $vesselData): bool
    {
        if (!$user->hasLineAccount()) {
            return false;
        }

        $message = "🚢 Vessel Arrival Update!\n\n";
        $message .= "Vessel: {$vesselData['vessel_name']}\n";
        $message .= "Voyage: {$vesselData['voyage_code']}\n";
        $message .= "Terminal: {$vesselData['terminal']}\n";
        $message .= "ETA: {$vesselData['eta']}\n\n";
        $message .= "🔗 Check details at: " . url('/shipments');

        return $this->sendTextMessage($user->line_user_id, $message);
    }

    /**
     * Send document reminder notification
     */
    public function sendDocumentReminder(User $user, array $documentData): bool
    {
        if (!$user->hasLineAccount()) {
            return false;
        }

        $message = "📋 Document Reminder!\n\n";
        $message .= "Document: {$documentData['type']}\n";
        $message .= "Shipment: {$documentData['shipment_ref']}\n";
        $message .= "Status: {$documentData['status']}\n";
        $message .= "Due Date: {$documentData['due_date']}\n\n";
        $message .= "🔗 Manage documents at: " . url('/shipments');

        return $this->sendTextMessage($user->line_user_id, $message);
    }

    /**
     * Send bulk notification to multiple users
     */
    public function sendBulkNotification(array $lineUserIds, string $message): array
    {
        $results = [];

        foreach ($lineUserIds as $lineUserId) {
            $results[$lineUserId] = $this->sendTextMessage($lineUserId, $message);
        }

        return $results;
    }

    /**
     * Build shipment message based on type and data
     */
    private function buildShipmentMessage(string $type, array $data): string
    {
        switch ($type) {
            case 'arrival':
                return "🚢 Your shipment has arrived!\n\n" .
                       "Invoice: {$data['invoice_number']}\n" .
                       "Customer: {$data['customer_name']}\n" .
                       "Vessel: {$data['vessel_name']}\n" .
                       "Terminal: {$data['terminal']}\n\n" .
                       "🔗 View details: " . url('/shipments');

            case 'ready_for_pickup':
                return "📦 Shipment ready for pickup!\n\n" .
                       "Invoice: {$data['invoice_number']}\n" .
                       "Location: {$data['pickup_location']}\n" .
                       "Contact: {$data['contact_info']}\n\n" .
                       "🔗 View details: " . url('/shipments');

            case 'customs_cleared':
                return "✅ Customs clearance completed!\n\n" .
                       "Invoice: {$data['invoice_number']}\n" .
                       "Status: Cleared\n" .
                       "Next step: Ready for delivery\n\n" .
                       "🔗 View details: " . url('/shipments');

            case 'delivery_scheduled':
                return "🚛 Delivery scheduled!\n\n" .
                       "Invoice: {$data['invoice_number']}\n" .
                       "Delivery Date: {$data['delivery_date']}\n" .
                       "Time Window: {$data['time_window']}\n\n" .
                       "🔗 View details: " . url('/shipments');

            default:
                return "📋 Shipment Update\n\n" .
                       "Invoice: {$data['invoice_number']}\n" .
                       "Status: {$data['status']}\n\n" .
                       "🔗 View details: " . url('/shipments');
        }
    }

    /**
     * Send welcome message to newly connected LINE users
     */
    public function sendWelcomeMessage(User $user): bool
    {
        if (!$user->hasLineAccount()) {
            return false;
        }

        $message = "🎉 Welcome to Eastern Air Logistics!\n\n";
        $message .= "Hi {$user->line_display_name}!\n\n";
        $message .= "Your LINE account has been successfully connected. ";
        $message .= "You'll now receive important updates about your shipments:\n\n";
        $message .= "📦 Shipment arrivals\n";
        $message .= "🚢 Vessel tracking updates\n";
        $message .= "📋 Document requirements\n";
        $message .= "🚛 Delivery notifications\n\n";
        $message .= "🔗 Manage your shipments: " . url('/shipments');

        return $this->sendTextMessage($user->line_user_id, $message);
    }

    /**
     * Test LINE connection by sending a test message
     */
    public function sendTestMessage(User $user): bool
    {
        if (!$user->hasLineAccount()) {
            return false;
        }

        $message = "🧪 Test Message from Eastern Air Logistics\n\n";
        $message .= "This is a test message to confirm your LINE connection is working properly.\n\n";
        $message .= "If you received this message, your LINE notifications are set up correctly! 🎉\n\n";
        $message .= "Time: " . now()->format('Y-m-d H:i:s');

        return $this->sendTextMessage($user->line_user_id, $message);
    }

    /**
     * Send welcome message to newly connected shipment clients
     */
    public function sendClientWelcomeMessage(ShipmentClient $client): bool
    {
        if (!$client->hasLineAccount()) {
            return false;
        }

        $shipment = $client->shipment;
        $message = "🎉 Welcome to Eastern Air Logistics!\n\n";
        $message .= "Hi {$client->line_display_name}!\n\n";
        $message .= "Your LINE account has been successfully connected to shipment tracking.\n\n";
        $message .= "📦 Shipment Details:\n";
        $message .= "• Invoice: {$shipment->invoice_number}\n";
        if ($shipment->vessel) {
            $message .= "• Vessel: {$shipment->vessel->name}\n";
            $message .= "• Voyage: {$shipment->voyage}\n";
        }
        if ($shipment->customer) {
            $message .= "• Customer: {$shipment->customer->company}\n";
        }
        $message .= "\nYou'll now receive important updates about this shipment:\n\n";
        $message .= "🚢 Vessel arrival updates\n";
        $message .= "📋 Document status changes\n";
        $message .= "🚛 Delivery notifications\n";
        $message .= "⏰ ETA updates\n\n";
        $message .= "Thank you for choosing Eastern Air Logistics! 🌟";

        return $this->sendTextMessage($client->line_user_id, $message);
    }

    /**
     * Send shipment ETA update to client
     */
    public function sendShipmentEtaUpdate(ShipmentClient $client): bool
    {
        if (!$client->hasLineAccount()) {
            return false;
        }

        $shipment = $client->shipment;
        $message = "📅 Shipment ETA Update\n\n";
        $message .= "Dear {$client->client_name},\n\n";
        $message .= "📦 Shipment Details:\n";
        $message .= "• Invoice: {$shipment->invoice_number}\n";
        $message .= "• HBL: {$shipment->hbl_number}\n";
        if ($shipment->vessel) {
            $message .= "• Vessel: {$shipment->vessel->name}\n";
            $message .= "• Voyage: {$shipment->voyage}\n";
        }
        $message .= "• Status: " . ucfirst(str_replace('_', ' ', $shipment->status)) . "\n";

        if ($shipment->planned_delivery_date) {
            $message .= "• Planned Delivery: {$shipment->planned_delivery_date->format('M d, Y')}\n";
        }

        $message .= "\n🚢 Current Status:\n";
        $message .= "• Customs Clearance: " . ucfirst($shipment->customs_clearance_status) . "\n";
        $message .= "• DO Status: " . ucfirst($shipment->do_status) . "\n";

        if ($shipment->pickup_location) {
            $message .= "• Pickup Location: {$shipment->pickup_location}\n";
        }

        $message .= "\n📞 For any questions, please contact Eastern Air Logistics.\n";
        $message .= "\nTime: " . now()->format('M d, Y H:i');

        return $this->sendTextMessage($client->line_user_id, $message);
    }

    /**
     * Send shipment delay notification to client
     */
    public function sendShipmentDelayNotification(ShipmentClient $client, string $reason, $newEta = null): bool
    {
        if (!$client->hasLineAccount()) {
            return false;
        }

        $shipment = $client->shipment;
        $message = "⚠️ Shipment Delay Notification\n\n";
        $message .= "Dear {$client->client_name},\n\n";
        $message .= "We regret to inform you of a delay in your shipment:\n\n";
        $message .= "📦 Shipment: {$shipment->invoice_number}\n";
        $message .= "🚢 Vessel: " . ($shipment->vessel ? $shipment->vessel->name : 'N/A') . "\n";
        $message .= "📋 Reason: {$reason}\n";

        if ($newEta) {
            $message .= "🕒 New ETA: {$newEta}\n";
        }

        $message .= "\nWe apologize for any inconvenience caused and will keep you updated.\n\n";
        $message .= "📞 Contact us for more information.\n";
        $message .= "\nEastern Air Logistics Team";

        return $this->sendTextMessage($client->line_user_id, $message);
    }
}