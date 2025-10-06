<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_requested_delivery_date',
        'hbl_number',
        'mbl_number',
        'invoice_number',
        'quantity_days',
        'do_status',
        'weight_kgm',
        'quantity_number',
        'quantity_unit',
        'vessel_id',
        'voyage',
        'customer_id',
        'port_terminal',
        'shipping_team',
        'cs_reference',
        'joint_pickup',
        'customs_clearance_status',
        'overtime_status',
        'status',
        'vessel_loading_status',
        'thai_status',
        'planned_delivery_date',
        'actual_delivery_date',
        'last_eta_check_date',
        'bot_received_eta_date',
        'tracking_status',
        'notes',
        'cargo_details',
        'pickup_location',
        'is_departed',
    ];

    protected $casts = [
        'client_requested_delivery_date' => 'datetime',
        'planned_delivery_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'last_eta_check_date' => 'datetime',
        'bot_received_eta_date' => 'datetime',
        'do_pickup_date' => 'datetime',
        'cargo_details' => 'array',
        'total_cost' => 'decimal:2',
        'weight_kgm' => 'decimal:2',
        'quantity_number' => 'decimal:2',
        'quantity_days' => 'integer',
        'is_departed' => 'boolean',
    ];

    /**
     * Get the customer that owns the shipment.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the vessel that carries the shipment.
     */
    public function vessel()
    {
        return $this->belongsTo(Vessel::class);
    }

    /**
     * Get the documents for the shipment.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the shipment clients for LINE notifications.
     */
    public function shipmentClients()
    {
        return $this->hasMany(ShipmentClient::class);
    }

    /**
     * Get the ETA check logs for this shipment.
     */
    public function etaCheckLogs()
    {
        return $this->hasMany(EtaCheckLog::class)->recent();
    }

    /**
     * Get active shipment clients.
     */
    public function activeShipmentClients()
    {
        return $this->shipmentClients()->where('is_active', true)->where('expires_at', '>', now());
    }

    /**
     * Get pending documents.
     */
    public function pendingDocuments()
    {
        return $this->documents()->where('status', 'pending');
    }

    /**
     * Get approved documents.
     */
    public function approvedDocuments()
    {
        return $this->documents()->where('status', 'approved');
    }

    /**
     * Check if shipment is ready for delivery.
     */
    public function isReadyForDelivery()
    {
        return $this->documents()->where('status', 'approved')->count() > 0 
               && $this->vessel->status === 'arrived';
    }

    /**
     * Get shipments by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get active shipments (not completed).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'in-progress');
    }

    /**
     * Get shipments requiring attention.
     */
    public function scopeRequiringAttention($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'new')
              ->orWhere('status', 'documents_preparation')
              ->orWhere('planned_delivery_date', '<=', now()->addDays(2));
        });
    }


    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'in-progress' => 'blue',
            'completed' => 'green',
            default => 'gray'
        };
    }
}
