<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Revolution\Line\Messaging\LineBot;
use Revolution\Line\Messaging\TextMessageBuilder;
use Revolution\Line\Messaging\FlexMessageBuilder;

class LineMessagingService
{
    protected $lineBot;

    public function __construct()
    {
        // Initialize LINE Bot with channel token
        $this->lineBot = app(LineBot::class);
    }

    /**
     * Send a text message to a specific LINE user
     */
    public function sendTextMessage(string $lineUserId, string $message): bool
    {
        try {
            $textMessage = new TextMessageBuilder($message);
            $response = $this->lineBot->pushMessage($lineUserId, $textMessage);

            if ($response->isSucceeded()) {
                Log::info('LINE message sent successfully', [
                    'line_user_id' => $lineUserId,
                    'message' => $message
                ]);
                return true;
            } else {
                Log::error('Failed to send LINE message', [
                    'line_user_id' => $lineUserId,
                    'error' => $response->getRawBody()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('LINE messaging error: ' . $e->getMessage(), [
                'line_user_id' => $lineUserId,
                'message' => $message
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
}