<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Services\LineMessagingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LineLoginController extends Controller
{
    /**
     * Redirect to LINE Login
     */
    public function redirectToLine()
    {
        try {
            return Socialite::driver('line')->scopes(['profile', 'openid'])->redirect();
        } catch (\Exception $e) {
            Log::error('LINE Login redirect error: ' . $e->getMessage());
            return redirect()->route('profile')->with('error', 'Failed to connect to LINE. Please try again.');
        }
    }

    /**
     * Handle LINE Login callback
     */
    public function handleLineCallback()
    {
        try {
            $lineUser = Socialite::driver('line')->user();

            // Get the currently authenticated user
            $user = Auth::user();

            if (!$user) {
                return redirect()->route('login')->with('error', 'Please login first before connecting your LINE account.');
            }

            // Check if this LINE account is already connected to another user
            $existingUser = User::where('line_user_id', $lineUser->getId())->first();

            if ($existingUser && $existingUser->id !== $user->id) {
                return redirect()->route('profile')->with('error', 'This LINE account is already connected to another user.');
            }

            // Update user with LINE information
            $user->update([
                'line_user_id' => $lineUser->getId(),
                'line_display_name' => $lineUser->getName(),
                'line_picture_url' => $lineUser->getAvatar(),
                'line_connected_at' => now(),
            ]);

            Log::info('LINE account connected successfully', [
                'user_id' => $user->id,
                'line_user_id' => $lineUser->getId(),
                'line_display_name' => $lineUser->getName()
            ]);

            // Send welcome message via LINE
            try {
                $lineMessaging = new LineMessagingService();
                $lineMessaging->sendWelcomeMessage($user);
            } catch (\Exception $e) {
                Log::warning('Failed to send LINE welcome message: ' . $e->getMessage());
            }

            return redirect()->route('profile')->with('success', 'Your LINE account has been connected successfully!');

        } catch (\Exception $e) {
            Log::error('LINE Login callback error: ' . $e->getMessage());
            return redirect()->route('profile')->with('error', 'Failed to connect LINE account. Please try again.');
        }
    }

    /**
     * Disconnect LINE account
     */
    public function disconnectLine()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return redirect()->route('login');
            }

            // Remove LINE connection
            $user->update([
                'line_user_id' => null,
                'line_display_name' => null,
                'line_picture_url' => null,
                'line_connected_at' => null,
            ]);

            Log::info('LINE account disconnected', ['user_id' => $user->id]);

            return redirect()->route('profile')->with('success', 'Your LINE account has been disconnected.');

        } catch (\Exception $e) {
            Log::error('LINE disconnect error: ' . $e->getMessage());
            return redirect()->route('profile')->with('error', 'Failed to disconnect LINE account. Please try again.');
        }
    }
}
