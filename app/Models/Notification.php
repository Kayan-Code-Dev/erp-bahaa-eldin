<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

/**
 * Notification Model
 * 
 * Handles user notifications for various system events.
 */
class Notification extends Model
{
    use HasFactory, SerializesDates, LogsActivity;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'reference_type',
        'reference_id',
        'priority',
        'read_at',
        'dismissed_at',
        'action_url',
        'metadata',
        'scheduled_for',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'priority' => 'string', // Enum values cast as string for compatibility
    ];

    /**
     * Notification type constants
     */
    public const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';
    public const TYPE_OVERDUE_RETURN = 'overdue_return';
    public const TYPE_PAYMENT_DUE = 'payment_due';
    public const TYPE_ORDER_STATUS = 'order_status';
    public const TYPE_TAILORING_STATUS = 'tailoring_status';
    public const TYPE_LOW_INVENTORY = 'low_inventory';
    public const TYPE_CUSTODY_REMINDER = 'custody_reminder';
    public const TYPE_SYSTEM = 'system';
    
    // Workshop notification types
    public const TYPE_WORKSHOP_TRANSFER_INCOMING = 'workshop_transfer_incoming';
    public const TYPE_WORKSHOP_CLOTH_READY = 'workshop_cloth_ready';
    public const TYPE_TRANSFER_INCOMING = 'transfer_incoming';
    
    // Factory notification types
    public const TYPE_FACTORY_ORDER_NEW = 'factory_order_new';
    public const TYPE_FACTORY_ORDER_MODIFIED = 'factory_order_modified';
    public const TYPE_FACTORY_DELIVERY_REMINDER = 'factory_delivery_reminder';

    /**
     * Priority constants
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * Get notification types with labels
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_APPOINTMENT_REMINDER => 'Appointment Reminder',
            self::TYPE_OVERDUE_RETURN => 'Overdue Return',
            self::TYPE_PAYMENT_DUE => 'Payment Due',
            self::TYPE_ORDER_STATUS => 'Order Status Change',
            self::TYPE_TAILORING_STATUS => 'Tailoring Status Change',
            self::TYPE_LOW_INVENTORY => 'Low Inventory Alert',
            self::TYPE_CUSTODY_REMINDER => 'Custody Reminder',
            self::TYPE_SYSTEM => 'System Notification',
            // Workshop notification types
            self::TYPE_WORKSHOP_TRANSFER_INCOMING => 'Workshop Transfer Incoming',
            self::TYPE_WORKSHOP_CLOTH_READY => 'Workshop Cloth Ready',
            self::TYPE_TRANSFER_INCOMING => 'Transfer Incoming',
            // Factory notification types
            self::TYPE_FACTORY_ORDER_NEW => 'New Factory Order',
            self::TYPE_FACTORY_ORDER_MODIFIED => 'Factory Order Modified',
            self::TYPE_FACTORY_DELIVERY_REMINDER => 'Factory Delivery Reminder',
        ];
    }

    /**
     * Get priority levels with labels
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user this notification belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the referenced entity (polymorphic)
     */
    public function reference()
    {
        return $this->morphTo('reference');
    }

    // ==================== ACCESSORS ====================

    /**
     * Check if notification is read
     */
    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Check if notification is dismissed
     */
    public function getIsDismissedAttribute(): bool
    {
        return $this->dismissed_at !== null;
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        $types = self::getTypes();
        return $types[$this->type] ?? $this->type;
    }

    /**
     * Get priority label
     */
    public function getPriorityLabelAttribute(): string
    {
        $priorities = self::getPriorities();
        return $priorities[$this->priority] ?? $this->priority;
    }

    /**
     * Check if notification is scheduled for future
     */
    public function getIsScheduledAttribute(): bool
    {
        return $this->scheduled_for !== null && $this->scheduled_for->isFuture();
    }

    // ==================== SCOPES ====================

    /**
     * Filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Filter read notifications
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Filter undismissed notifications
     */
    public function scopeUndismissed($query)
    {
        return $query->whereNull('dismissed_at');
    }

    /**
     * Filter by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Filter by priority
     */
    public function scopeOfPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Filter high priority (high and urgent)
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Filter ready to send (scheduled time has passed)
     */
    public function scopeReadyToSend($query)
    {
        return $query->whereNull('sent_at')
            ->where(function ($q) {
                $q->whereNull('scheduled_for')
                  ->orWhere('scheduled_for', '<=', now());
            });
    }

    /**
     * Filter sent notifications
     */
    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    /**
     * Filter by reference
     */
    public function scopeForReference($query, $type, $id)
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }

    /**
     * Filter recent notifications
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ==================== METHODS ====================

    /**
     * Mark notification as read
     */
    public function markAsRead(): bool
    {
        if ($this->read_at === null) {
            $this->read_at = now();
            return $this->save();
        }
        return true;
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(): bool
    {
        $this->read_at = null;
        return $this->save();
    }

    /**
     * Dismiss notification
     */
    public function dismiss(): bool
    {
        $this->dismissed_at = now();
        return $this->save();
    }

    /**
     * Mark as sent
     */
    public function markAsSent(): bool
    {
        $this->sent_at = now();
        return $this->save();
    }
}

