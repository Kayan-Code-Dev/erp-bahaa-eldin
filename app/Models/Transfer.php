<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class Transfer extends Model
{
    use HasFactory, SerializesDates, LogsActivity;

    protected $fillable = [
        'from_entity_type',
        'from_entity_id',
        'to_entity_type',
        'to_entity_id',
        'transfer_date',
        'notes',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        // Map enum values to class names for morphTo relationships
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'branch' => \App\Models\Branch::class,
            'workshop' => \App\Models\Workshop::class,
            'factory' => \App\Models\Factory::class,
        ]);
    }

    public function fromEntity()
    {
        return $this->morphTo('fromEntity');
    }

    public function toEntity()
    {
        return $this->morphTo('toEntity');
    }

    public function clothes()
    {
        return $this->belongsToMany(Cloth::class, 'transfer_items')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }

    /**
     * Check if all items are approved
     */
    public function allItemsApproved()
    {
        $totalItems = $this->items()->count();
        $approvedItems = $this->items()->where('status', 'approved')->count();
        return $totalItems > 0 && $totalItems === $approvedItems;
    }

    /**
     * Check if some items are approved (but not all)
     */
    public function someItemsApproved()
    {
        $totalItems = $this->items()->count();
        $approvedItems = $this->items()->where('status', 'approved')->count();
        $rejectedItems = $this->items()->where('status', 'rejected')->count();
        $pendingItems = $this->items()->where('status', 'pending')->count();

        return $approvedItems > 0 && ($pendingItems > 0 || $rejectedItems > 0);
    }

    /**
     * Check if some items are rejected (but not all, and no items are approved)
     */
    public function someItemsRejected()
    {
        $totalItems = $this->items()->count();
        $approvedItems = $this->items()->where('status', 'approved')->count();
        $rejectedItems = $this->items()->where('status', 'rejected')->count();
        $pendingItems = $this->items()->where('status', 'pending')->count();

        // Some items rejected, but not all, and no items are approved
        return $rejectedItems > 0 && $rejectedItems < $totalItems && $approvedItems === 0;
    }

    /**
     * Check if transfer should be partially_approved
     * This is true when: all items are not pending, some are approved, and some are rejected
     */
    public function hasMixedApprovedAndRejected()
    {
        $totalItems = $this->items()->count();
        $approvedItems = $this->items()->where('status', 'approved')->count();
        $rejectedItems = $this->items()->where('status', 'rejected')->count();
        $pendingItems = $this->items()->where('status', 'pending')->count();

        // All items are not pending (no pending items), some are approved, and some are rejected
        return $pendingItems === 0 && $approvedItems > 0 && $rejectedItems > 0;
    }

    /**
     * Update transfer status based on items status
     */
    public function updateStatus()
    {
        $totalItems = $this->items()->count();
        $approvedItems = $this->items()->where('status', 'approved')->count();
        $rejectedItems = $this->items()->where('status', 'rejected')->count();
        $pendingItems = $this->items()->where('status', 'pending')->count();

        if ($totalItems > 0 && $totalItems === $approvedItems) {
            // All items approved
            $this->update(['status' => 'approved']);
        } elseif ($totalItems > 0 && $totalItems === $rejectedItems) {
            // All items rejected
            $this->update(['status' => 'rejected']);
        } elseif ($totalItems > 0 && $totalItems === $pendingItems) {
            // All pending
            $this->update(['status' => 'pending']);
        } elseif ($this->hasMixedApprovedAndRejected()) {
            // Some approved AND some rejected AND no pending → partially_approved (only case)
            $this->update(['status' => 'partially_approved']);
        } else {
            // Otherwise → partially_pending
            $this->update(['status' => 'partially_pending']);
        }
    }

    public function actions()
    {
        return $this->hasMany(TransferAction::class);
    }

    public function createdBy()
    {
        return $this->hasOne(TransferAction::class)
                    ->where('action', 'created')
                    ->with('user');
    }

    public function approvedBy()
    {
        return $this->hasOne(TransferAction::class)
                    ->where('action', 'approved')
                    ->with('user');
    }

    public function rejectedBy()
    {
        return $this->hasOne(TransferAction::class)
                    ->where('action', 'rejected')
                    ->with('user');
    }

    public function deletedBy()
    {
        return $this->hasOne(TransferAction::class)
                    ->where('action', 'deleted')
                    ->with('user');
    }
}
