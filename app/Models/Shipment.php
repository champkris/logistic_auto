<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'hbl_number',
        'mbl_number',
        'invoice_number',
        'quantity_days',
        'do_status',
        'weight_kgm',
        'fcl_type',
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
        'notes',
        'cargo_details',
        'shipping_line',
    ];

    protected $casts = [
        'planned_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'do_pickup_date' => 'datetime',
        'cargo_details' => 'array',
        'total_cost' => 'decimal:2',
        'weight_kgm' => 'decimal:2',
        'quantity_days' => 'integer',
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
     * Automatically update status based on customs clearance and DO status
     */
    public function updateAutomaticStatus()
    {
        if ($this->customs_clearance_status === 'received' && $this->do_status === 'received') {
            $this->status = 'completed';
        } else {
            $this->status = 'in-progress';
        }
    }

    /**
     * Boot method to add model event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Update status whenever a shipment is saved
        static::saving(function ($shipment) {
            $shipment->updateAutomaticStatus();
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
