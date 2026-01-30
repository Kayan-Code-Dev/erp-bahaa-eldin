<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;

class WorkshopLog extends Model
{
    use HasFactory, SerializesDates;

    protected $fillable = [
        'workshop_id',
        'cloth_id',
        'transfer_id',
        'action',
        'cloth_status',
        'notes',
        'received_at',
        'returned_at',
        'user_id',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /**
     * Action type constants
     */
    const ACTIONS = [
        'received' => 'Cloth Received',
        'status_changed' => 'Status Changed',
        'returned' => 'Cloth Returned',
    ];

    /**
     * Cloth status constants
     */
    const CLOTH_STATUSES = [
        'received' => 'Received',
        'processing' => 'Processing',
        'ready_for_delivery' => 'Ready for Delivery',
    ];

    /**
     * Get the workshop this log belongs to
     */
    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * Get the cloth this log is about
     */
    public function cloth()
    {
        return $this->belongsTo(Cloth::class);
    }

    /**
     * Get the transfer associated with this log (if any)
     */
    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Get the user who performed this action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the action label
     */
    public function getActionLabelAttribute()
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    /**
     * Get the status label
     */
    public function getStatusLabelAttribute()
    {
        return self::CLOTH_STATUSES[$this->cloth_status] ?? $this->cloth_status;
    }

    /**
     * Scope: filter by cloth
     */
    public function scopeForCloth($query, $clothId)
    {
        return $query->where('cloth_id', $clothId);
    }

    /**
     * Scope: filter by workshop
     */
    public function scopeForWorkshop($query, $workshopId)
    {
        return $query->where('workshop_id', $workshopId);
    }

    /**
     * Scope: filter by action type
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope: filter by cloth status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('cloth_status', $status);
    }

    /**
     * Scope: get latest log per cloth in workshop
     */
    public function scopeLatestPerCloth($query)
    {
        return $query->whereIn('id', function($q) {
            $q->selectRaw('MAX(id)')
              ->from('workshop_logs')
              ->groupBy('cloth_id', 'workshop_id');
        });
    }
}





