<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'address',
        'notification_preferences',
        'is_active',
    ];

    protected $casts = [
        'notification_preferences' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the shipments for the customer.
     */
    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Get active shipments for the customer.
     */
    public function activeShipments()
    {
        return $this->shipments()->whereNotIn('status', ['delivered', 'completed']);
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
