<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_number',
        'consignee',
        'hbl_number',
        'mbl_number',
        'invoice_number',
        'vessel_id',
        'customer_id',
        'port_of_discharge',
        'status',
        'planned_delivery_date',
        'actual_delivery_date',
        'total_cost',
        'notes',
        'cargo_details',
    ];

    protected $casts = [
        'planned_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'cargo_details' => 'array',
        'total_cost' => 'decimal:2',
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
        return $query->whereNotIn('status', ['delivered', 'completed']);
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
            'new' => 'blue',
            'planning' => 'yellow',
            'documents_preparation' => 'orange',
            'customs_clearance' => 'purple',
            'ready_for_delivery' => 'green',
            'in_transit' => 'indigo',
            'delivered' => 'emerald',
            'completed' => 'gray',
            default => 'gray'
        };
    }
}
