<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;
use Carbon\Carbon;

/**
 * Rent/Appointment Model
 * 
 * This model handles all types of appointments in the atelier system:
 * - Rental deliveries and returns
 * - Measurement appointments
 * - Tailoring pickups and deliveries
 * - Fittings
 * - General appointments
 */
class Rent extends Model
{
    use HasFactory, SerializesDates, LogsActivity;

    protected $fillable = [
        'client_id',
        'branch_id',
        'cloth_id',
        'order_id',
        'cloth_order_id',
        'appointment_type',
        'title',
        'delivery_date',
        'appointment_date', // Alias for delivery_date
        'appointment_time',
        'return_date',
        'return_time',
        'days_of_rent',
        'status',
        'notes',
        'reminder_sent',
        'reminder_sent_at',
        'created_by',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'return_date' => 'date',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => 'string', // Enum values cast as string for compatibility
    ];

    /**
     * Handle appointment_date attribute (alias for delivery_date)
     */
    public function setAppointmentDateAttribute($value)
    {
        $this->attributes['delivery_date'] = $value;
    }

    /**
     * Appointment types
     */
    public const TYPE_RENTAL_DELIVERY = 'rental_delivery';
    public const TYPE_RENTAL_RETURN = 'rental_return';
    public const TYPE_MEASUREMENT = 'measurement';
    public const TYPE_TAILORING_PICKUP = 'tailoring_pickup';
    public const TYPE_TAILORING_DELIVERY = 'tailoring_delivery';
    public const TYPE_FITTING = 'fitting';
    public const TYPE_OTHER = 'other';

    /**
     * Status constants
     */
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_RESCHEDULED = 'rescheduled';
    // Legacy statuses for backward compatibility
    public const STATUS_ACTIVE = 'active';

    /**
     * Get all appointment types with labels
     */
    public static function getAppointmentTypes(): array
    {
        return [
            self::TYPE_RENTAL_DELIVERY => 'Rental Delivery',
            self::TYPE_RENTAL_RETURN => 'Rental Return',
            self::TYPE_MEASUREMENT => 'Measurement',
            self::TYPE_TAILORING_PICKUP => 'Tailoring Pickup',
            self::TYPE_TAILORING_DELIVERY => 'Tailoring Delivery',
            self::TYPE_FITTING => 'Fitting',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get all statuses with labels
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_NO_SHOW => 'No Show',
            self::STATUS_RESCHEDULED => 'Rescheduled',
            self::STATUS_ACTIVE => 'Active', // Legacy
        ];
    }

    // ==================== RELATIONSHIPS ====================

    public function cloth()
    {
        return $this->belongsTo(Cloth::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the cloth_order pivot record for this rent
     */
    public function getClothOrderPivot()
    {
        if (!$this->order || !$this->cloth_id) {
            return null;
        }

        return $this->order->items()
            ->where('clothes.id', $this->cloth_id)
            ->first()?->pivot;
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the full datetime of the appointment
     */
    public function getAppointmentDateTimeAttribute(): ?Carbon
    {
        if (!$this->delivery_date) {
            return null;
        }
        
        if ($this->appointment_time) {
            return $this->delivery_date->setTimeFromTimeString($this->appointment_time);
        }
        
        return $this->delivery_date;
    }

    /**
     * Get the full datetime of the return
     */
    public function getReturnDateTimeAttribute(): ?Carbon
    {
        if (!$this->return_date) {
            return null;
        }
        
        if ($this->return_time) {
            return $this->return_date->setTimeFromTimeString($this->return_time);
        }
        
        return $this->return_date;
    }

    /**
     * Get a display-friendly title
     */
    public function getDisplayTitleAttribute(): string
    {
        if ($this->title) {
            return $this->title;
        }

        $types = self::getAppointmentTypes();
        $type = $types[$this->appointment_type] ?? 'Appointment';
        
        if ($this->client) {
            return "{$type} - {$this->client->first_name} {$this->client->last_name}";
        }
        
        return $type;
    }

    /**
     * Check if the appointment is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->isCompleted() || $this->isCancelled()) {
            return false;
        }
        
        $appointmentDate = $this->appointment_date_time ?? $this->delivery_date;
        
        return $appointmentDate && $appointmentDate->isPast();
    }

    /**
     * Check if this is a rental type appointment
     */
    public function getIsRentalAttribute(): bool
    {
        return in_array($this->appointment_type, [
            self::TYPE_RENTAL_DELIVERY,
            self::TYPE_RENTAL_RETURN,
        ]);
    }

    /**
     * Check if this is a tailoring type appointment
     */
    public function getIsTailoringAttribute(): bool
    {
        return in_array($this->appointment_type, [
            self::TYPE_TAILORING_PICKUP,
            self::TYPE_TAILORING_DELIVERY,
        ]);
    }

    // ==================== STATUS CHECKS ====================

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ACTIVE, // Legacy
        ]);
    }

    public function canBeModified(): bool
    {
        return !in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ]);
    }

    // ==================== ACTIONS ====================

    /**
     * Confirm the appointment
     */
    public function confirm(): bool
    {
        if (!$this->canBeModified()) {
            return false;
        }

        $this->status = self::STATUS_CONFIRMED;
        return $this->save();
    }

    /**
     * Start the appointment
     */
    public function startProgress(): bool
    {
        if (!$this->canBeModified()) {
            return false;
        }

        $this->status = self::STATUS_IN_PROGRESS;
        return $this->save();
    }

    /**
     * Complete the appointment
     */
    public function complete(User $user): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->completed_by = $user->id;
        return $this->save();
    }

    /**
     * Cancel the appointment
     */
    public function cancel(?string $reason = null): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Cancelled: {$reason}";
        }
        return $this->save();
    }

    /**
     * Mark as no-show
     */
    public function markNoShow(): bool
    {
        if ($this->isCompleted() || $this->isCancelled()) {
            return false;
        }

        $this->status = self::STATUS_NO_SHOW;
        return $this->save();
    }

    /**
     * Reschedule the appointment
     */
    public function reschedule(Carbon $newDate, ?string $newTime = null): bool
    {
        if (!$this->canBeModified()) {
            return false;
        }

        $oldDate = $this->delivery_date->format('Y-m-d');
        $oldTime = $this->appointment_time;

        $this->delivery_date = $newDate;
        $this->appointment_time = $newTime;
        $this->status = self::STATUS_RESCHEDULED;
        $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
            "Rescheduled from {$oldDate}" . ($oldTime ? " {$oldTime}" : '');
        
        return $this->save();
    }

    /**
     * Mark reminder as sent
     */
    public function markReminderSent(): bool
    {
        $this->reminder_sent = true;
        $this->reminder_sent_at = now();
        return $this->save();
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_ACTIVE, // Legacy
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForCloth($query, $clothId)
    {
        return $query->where('cloth_id', $clothId);
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('appointment_type', $type);
    }

    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('delivery_date', $date);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('delivery_date', [$startDate, $endDate]);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('delivery_date', '>=', today())
            ->active()
            ->orderBy('delivery_date')
            ->orderBy('appointment_time');
    }

    public function scopeOverdue($query)
    {
        return $query->where('delivery_date', '<', today())
            ->active();
    }

    public function scopeNeedingReminder($query, int $daysBeforeAppointment = 1)
    {
        return $query->where('reminder_sent', false)
            ->whereDate('delivery_date', '<=', today()->addDays($daysBeforeAppointment))
            ->active();
    }

    public function scopeToday($query)
    {
        return $query->whereDate('delivery_date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('delivery_date', [
            today()->startOfWeek(),
            today()->endOfWeek(),
        ]);
    }

    // ==================== CONFLICT DETECTION ====================

    /**
     * Check if there's a scheduling conflict for a cloth on the given dates
     */
    public static function hasClothConflict(
        int $clothId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $excludeId = null
    ): bool {
        $query = self::where('cloth_id', $clothId)
            ->active()
            ->where(function ($q) use ($startDate, $endDate) {
                // Check for overlap: existing rent overlaps with new dates
                $q->where(function ($inner) use ($startDate, $endDate) {
                    $inner->where('delivery_date', '<=', $endDate)
                          ->where('return_date', '>=', $startDate);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get conflicting appointments for a cloth
     */
    public static function getClothConflicts(
        int $clothId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $excludeId = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = self::where('cloth_id', $clothId)
            ->active()
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('delivery_date', '<=', $endDate)
                  ->where('return_date', '>=', $startDate);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    /**
     * Get unavailable dates for a cloth
     */
    public static function getClothUnavailableDates(
        int $clothId,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null
    ): array {
        $fromDate = $fromDate ?? today();
        $toDate = $toDate ?? today()->addMonths(3);

        $rents = self::where('cloth_id', $clothId)
            ->active()
            ->where('delivery_date', '<=', $toDate)
            ->where('return_date', '>=', $fromDate)
            ->get(['delivery_date', 'return_date']);

        $unavailableDates = [];
        
        foreach ($rents as $rent) {
            $current = $rent->delivery_date->copy();
            while ($current <= $rent->return_date) {
                $unavailableDates[] = $current->format('Y-m-d');
                $current->addDay();
            }
        }

        return array_unique($unavailableDates);
    }
}
