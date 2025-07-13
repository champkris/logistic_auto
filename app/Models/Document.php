<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'type',
        'document_name',
        'file_path',
        'status',
        'cost',
        'due_date',
        'received_date',
        'issued_by',
        'remarks',
    ];

    protected $casts = [
        'due_date' => 'date',
        'received_date' => 'date',
        'cost' => 'decimal:2',
    ];

    /**
     * Get the shipment that owns the document.
     */
    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Check if document is overdue.
     */
    public function isOverdue()
    {
        return $this->due_date && now()->greaterThan($this->due_date) && $this->status === 'pending';
    }

    /**
     * Get documents by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get pending documents.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get overdue documents.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('status', 'pending');
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'yellow',
            'received' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            'expired' => 'gray',
            default => 'gray'
        };
    }
}
