<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\LogsActivity;

class Permission extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'module',
        'action',
    ];

    /**
     * Get all roles that have this permission
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role')
                    ->withTimestamps();
    }

    /**
     * Scope a query to only include permissions for a specific module.
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope a query to only include permissions for a specific action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Create a permission name from module and action
     */
    public static function makeName(string $module, string $action): string
    {
        return "{$module}.{$action}";
    }

    /**
     * Parse a permission name into module and action
     */
    public static function parseName(string $name): array
    {
        $parts = explode('.', $name, 2);
        return [
            'module' => $parts[0] ?? '',
            'action' => $parts[1] ?? '',
        ];
    }

    /**
     * Find or create a permission by name
     */
    public static function findOrCreateByName(string $name, string $displayName = null, string $description = null): self
    {
        $parsed = self::parseName($name);
        
        return self::firstOrCreate(
            ['name' => $name],
            [
                'display_name' => $displayName ?? ucfirst(str_replace('.', ' ', $name)),
                'description' => $description,
                'module' => $parsed['module'],
                'action' => $parsed['action'],
            ]
        );
    }
}



