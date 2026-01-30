<?php

namespace App\Models\Traits;

use App\Models\ActivityLog;

/**
 * Trait for automatic activity logging on Eloquent models
 * 
 * Usage: Add `use LogsActivity;` to any model you want to track changes for.
 * 
 * By default, it logs created, updated, and deleted events.
 * 
 * You can customize behavior by overriding:
 * - $logAttributes: array of attributes to log (empty = all)
 * - $logExcept: array of attributes to exclude from logging
 * - $logOnlyDirty: if true, only log changed attributes
 * - $logName: human-readable name for the entity
 */
trait LogsActivity
{
    
    /**
     * Boot the trait
     */
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            if ($model->shouldLogActivity()) {
                $model->logActivityCreated();
            }
        });

        static::updated(function ($model) {
            if ($model->shouldLogActivity() && $model->isDirty()) {
                $model->logActivityUpdated();
            }
        });

        static::deleted(function ($model) {
            if ($model->shouldLogActivity()) {
                $model->logActivityDeleted();
            }
        });

        // Log restored events if using SoftDeletes
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                if ($model->shouldLogActivity()) {
                    $model->logActivityRestored();
                }
            });
        }
    }

    /**
     * Log activity when model is created
     */
    protected function logActivityCreated(): void
    {
        ActivityLog::log(
            ActivityLog::ACTION_CREATED,
            $this,
            null,
            $this->getLoggableAttributes(),
            $this->getActivityDescription('created')
        );
    }

    /**
     * Log activity when model is updated
     */
    protected function logActivityUpdated(): void
    {
        $oldValues = $this->getOriginalLoggableAttributes();
        $newValues = $this->getLoggableAttributes();

        // Only log if there are actual changes in loggable attributes
        if (empty(array_diff_assoc($newValues, $oldValues))) {
            return;
        }

        ActivityLog::log(
            ActivityLog::ACTION_UPDATED,
            $this,
            $oldValues,
            $newValues,
            $this->getActivityDescription('updated')
        );
    }

    /**
     * Log activity when model is deleted
     */
    protected function logActivityDeleted(): void
    {
        ActivityLog::log(
            ActivityLog::ACTION_DELETED,
            $this,
            $this->getLoggableAttributes(),
            null,
            $this->getActivityDescription('deleted')
        );
    }

    /**
     * Log activity when model is restored
     */
    protected function logActivityRestored(): void
    {
        ActivityLog::log(
            ActivityLog::ACTION_RESTORED,
            $this,
            null,
            $this->getLoggableAttributes(),
            $this->getActivityDescription('restored')
        );
    }

    /**
     * Static flag to disable activity logging globally (useful for tests)
     */
    protected static bool $globalActivityLoggingEnabled = true;

    /**
     * Disable activity logging globally
     */
    public static function disableActivityLogging(): void
    {
        static::$globalActivityLoggingEnabled = false;
    }

    /**
     * Enable activity logging globally
     */
    public static function enableActivityLogging(): void
    {
        static::$globalActivityLoggingEnabled = true;
    }
    
    /**
     * Determine if activity should be logged
     */
    protected function shouldLogActivity(): bool
    {
        // Check global flag first
        if (!self::$globalActivityLoggingEnabled) {
            return false;
        }

        // Can be overridden in model to conditionally disable logging
        return property_exists($this, 'enableActivityLog') ? $this->enableActivityLog : true;
    }

    /**
     * Get attributes to be logged
     */
    protected function getLoggableAttributes(): array
    {
        $attributes = $this->getAttributes();

        // If specific attributes are defined, only log those
        if (property_exists($this, 'logAttributes') && !empty($this->logAttributes)) {
            $attributes = array_intersect_key($attributes, array_flip($this->logAttributes));
        }

        // Exclude certain attributes
        if (property_exists($this, 'logExcept') && !empty($this->logExcept)) {
            $attributes = array_diff_key($attributes, array_flip($this->logExcept));
        }

        // Always exclude sensitive attributes
        $sensitiveAttributes = ['password', 'remember_token', 'api_token'];
        $attributes = array_diff_key($attributes, array_flip($sensitiveAttributes));

        return $attributes;
    }

    /**
     * Get original values for loggable attributes
     */
    protected function getOriginalLoggableAttributes(): array
    {
        $original = $this->getOriginal();

        // Apply same filters as getLoggableAttributes
        if (property_exists($this, 'logAttributes') && !empty($this->logAttributes)) {
            $original = array_intersect_key($original, array_flip($this->logAttributes));
        }

        if (property_exists($this, 'logExcept') && !empty($this->logExcept)) {
            $original = array_diff_key($original, array_flip($this->logExcept));
        }

        $sensitiveAttributes = ['password', 'remember_token', 'api_token'];
        $original = array_diff_key($original, array_flip($sensitiveAttributes));

        return $original;
    }

    /**
     * Get description for activity log
     */
    protected function getActivityDescription(string $action): string
    {
        $entityName = $this->getActivityLogName();
        $identifier = $this->getActivityLogIdentifier();

        return ucfirst($action) . " {$entityName} {$identifier}";
    }

    /**
     * Get human-readable name for activity log
     */
    protected function getActivityLogName(): string
    {
        if (property_exists($this, 'logName') && $this->logName) {
            return $this->logName;
        }

        // Convert model class name to readable format
        $className = class_basename($this);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $className));
    }

    /**
     * Get identifier for activity log
     */
    protected function getActivityLogIdentifier(): string
    {
        // Try common name fields
        if ($this->name) {
            return "'{$this->name}'";
        }
        if ($this->title) {
            return "'{$this->title}'";
        }
        if ($this->code) {
            return "({$this->code})";
        }

        return "#{$this->id}";
    }

    /**
     * Manually log a custom activity
     */
    public function logActivity(string $action, ?string $description = null, ?array $metadata = null): ActivityLog
    {
        return ActivityLog::log(
            $action,
            $this,
            null,
            $this->getLoggableAttributes(),
            $description ?? $this->getActivityDescription($action),
            $metadata
        );
    }

    /**
     * Get activity logs for this model
     */
    public function activityLogs()
    {
        return ActivityLog::forEntity(get_class($this), $this->id)
                          ->orderBy('created_at', 'desc');
    }

    /**
     * Get recent activity logs for this model
     */
    public function getRecentActivityAttribute()
    {
        return $this->activityLogs()->limit(10)->get();
    }
}





