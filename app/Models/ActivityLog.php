<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;

class ActivityLog extends Model
{
    use HasFactory, SerializesDates;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'entity_name',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'branch_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
    ];

    // Action constants
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_PASSWORD_CHANGED = 'password_changed';
    public const ACTION_PASSWORD_RESET = 'password_reset';
    public const ACTION_VIEWED = 'viewed';
    public const ACTION_EXPORTED = 'exported';
    public const ACTION_IMPORTED = 'imported';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_STATUS_CHANGED = 'status_changed';
    public const ACTION_API_REQUEST = 'api_request';

    public const ACTIONS = [
        self::ACTION_CREATED => 'Created',
        self::ACTION_UPDATED => 'Updated',
        self::ACTION_DELETED => 'Deleted',
        self::ACTION_RESTORED => 'Restored',
        self::ACTION_LOGIN => 'Login',
        self::ACTION_LOGOUT => 'Logout',
        self::ACTION_LOGIN_FAILED => 'Login Failed',
        self::ACTION_PASSWORD_CHANGED => 'Password Changed',
        self::ACTION_PASSWORD_RESET => 'Password Reset',
        self::ACTION_VIEWED => 'Viewed',
        self::ACTION_EXPORTED => 'Exported',
        self::ACTION_IMPORTED => 'Imported',
        self::ACTION_APPROVED => 'Approved',
        self::ACTION_REJECTED => 'Rejected',
        self::ACTION_STATUS_CHANGED => 'Status Changed',
        self::ACTION_API_REQUEST => 'API Request',
    ];

    /**
     * User who performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Branch where action occurred
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the entity (polymorphic)
     */
    public function entity()
    {
        if (!$this->entity_type || !$this->entity_id) {
            return null;
        }

        return $this->entity_type::find($this->entity_id);
    }

    /**
     * Scope by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by action
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope by entity type
     */
    public function scopeForEntityType($query, $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope for specific entity
     */
    public function scopeForEntity($query, $entityType, $entityId)
    {
        return $query->where('entity_type', $entityType)
                     ->where('entity_id', $entityId);
    }

    /**
     * Scope for date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for branch
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Get action label
     */
    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst($this->action);
    }

    /**
     * Get entity type short name
     */
    public function getEntityTypeShortAttribute(): string
    {
        if (!$this->entity_type) {
            return '';
        }
        return class_basename($this->entity_type);
    }

    /**
     * Get formatted description
     */
    public function getFormattedDescriptionAttribute(): string
    {
        if ($this->description) {
            return $this->description;
        }

        $userName = $this->user->name ?? 'System';
        $entityType = $this->entity_type_short;
        $entityName = $this->entity_name ?? "#{$this->entity_id}";

        return "{$userName} {$this->action_label} {$entityType} {$entityName}";
    }

    /**
     * Get changed fields summary
     */
    public function getChangesSummaryAttribute(): string
    {
        if (!$this->changed_fields) {
            return '';
        }

        return implode(', ', $this->changed_fields);
    }

    /**
     * Log an activity
     */
    public static function log(
        string $action,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        $user = auth()->user();
        $request = request();

        // Determine changed fields
        $changedFields = null;
        if ($oldValues && $newValues) {
            $changedFields = array_keys(array_diff_assoc($newValues, $oldValues));
        }

        return self::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entity ? get_class($entity) : null,
            'entity_id' => $entity?->id,
            'entity_name' => $entity ? ($entity->name ?? $entity->title ?? null) : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'branch_id' => $user?->employee?->primaryBranch()?->id,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log entity created
     */
    public static function logCreated(Model $entity, ?string $description = null): self
    {
        return self::log(
            self::ACTION_CREATED,
            $entity,
            null,
            $entity->getAttributes(),
            $description
        );
    }

    /**
     * Log entity updated
     */
    public static function logUpdated(Model $entity, array $oldValues, ?string $description = null): self
    {
        return self::log(
            self::ACTION_UPDATED,
            $entity,
            $oldValues,
            $entity->getAttributes(),
            $description
        );
    }

    /**
     * Log entity deleted
     */
    public static function logDeleted(Model $entity, ?string $description = null): self
    {
        return self::log(
            self::ACTION_DELETED,
            $entity,
            $entity->getAttributes(),
            null,
            $description
        );
    }

    /**
     * Log user login
     */
    public static function logLogin(User $user): self
    {
        return self::log(
            self::ACTION_LOGIN,
            $user,
            null,
            null,
            "{$user->name} logged in"
        );
    }

    /**
     * Log user logout
     */
    public static function logLogout(User $user): self
    {
        return self::log(
            self::ACTION_LOGOUT,
            $user,
            null,
            null,
            "{$user->name} logged out"
        );
    }

    /**
     * Log failed login attempt
     */
    public static function logLoginFailed(string $email): self
    {
        return self::create([
            'action' => self::ACTION_LOGIN_FAILED,
            'description' => "Failed login attempt for email: {$email}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => ['email' => $email],
        ]);
    }
}


