<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShipmentClient extends Model
{
    protected $fillable = [
        'shipment_id',
        'line_user_id',
        'line_display_name',
        'line_picture_url',
        'client_name',
        'client_email',
        'client_phone',
        'verification_token',
        'line_connected_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'line_connected_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function hasLineAccount(): bool
    {
        return !empty($this->line_user_id);
    }

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public static function generateVerificationToken(): string
    {
        return Str::random(32);
    }

    public function getLoginUrl(): string
    {
        return url('/client/line/connect/' . $this->verification_token);
    }

    /**
     * Get all active shipments for a LINE user
     */
    public static function getShipmentsForLineUser(string $lineUserId)
    {
        return static::where('line_user_id', $lineUserId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('shipment.customer', 'shipment.vessel')
            ->get();
    }

    /**
     * Check if a LINE user has access to a specific shipment
     */
    public static function hasAccessToShipment(string $lineUserId, int $shipmentId): bool
    {
        return static::where('line_user_id', $lineUserId)
            ->where('shipment_id', $shipmentId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
