<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SiamComChatbotEtaRequest extends Model
{
    // Specify the table name since it doesn't follow Laravel naming convention
    protected $table = 'siam_com_chatbot_eta_requests';
    
    protected $fillable = [
        'group_id',
        'vessel_name', 
        'voyage_code',
        'last_known_eta',
        'status',
        'last_asked_at',
        'conversation_history'
    ];

    protected $casts = [
        'last_known_eta' => 'datetime',
        'last_asked_at' => 'datetime',
        'conversation_history' => 'array'
    ];

    // Check if request is older than X hours (for rate limiting) OR if ETA is missing
    public function shouldAskNew($hours = 3)
    {
        // First condition: Check if never asked before
        if (!$this->last_asked_at) {
            return true;
        }
        
        // Second condition: Check if ETA is null/empty (always ask if no ETA data)
        if (is_null($this->last_known_eta) || empty($this->last_known_eta)) {
            return true;
        }
        
        // Third condition: Check if enough time has passed (rate limiting)
        return $this->last_asked_at->diffInHours(now()) >= $hours;
    }

    // Add conversation message to history
    public function addConversation($message, $sender = 'user')
    {
        $history = $this->conversation_history ?? [];
        $history[] = [
            'sender' => $sender,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ];
        
        $this->update(['conversation_history' => $history]);
    }

    // Get hours since last request
    public function getHoursSinceLastRequest()
    {
        if (!$this->last_asked_at) {
            return 999; // Very old
        }
        
        return $this->last_asked_at->diffInHours(now());
    }

    // Get the reason why we need to ask new ETA
    public function getAskNewReason($hours = 3)
    {
        if (!$this->last_asked_at) {
            return 'never_asked';
        }
        
        if (is_null($this->last_known_eta) || empty($this->last_known_eta)) {
            return 'no_eta_data';
        }
        
        if ($this->last_asked_at->diffInHours(now()) >= $hours) {
            return 'time_expired';
        }
        
        return 'use_cached';
    }
}
