<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\LogsActivity;

class Role extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'description'];

    /**
     * Get all users that have this role
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get all permissions for this role
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
                    ->withTimestamps();
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * Check if role has any of the given permissions
     */
    public function hasAnyPermission(array $permissionNames): bool
    {
        return $this->permissions()->whereIn('name', $permissionNames)->exists();
    }

    /**
     * Check if role has all of the given permissions
     */
    public function hasAllPermissions(array $permissionNames): bool
    {
        $count = $this->permissions()->whereIn('name', $permissionNames)->count();
        return $count === count($permissionNames);
    }

    /**
     * Assign a permission to this role
     */
    public function assignPermission($permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->firstOrFail();
        }

        if (!$this->hasPermission($permission->name)) {
            $this->permissions()->attach($permission->id);
        }
    }

    /**
     * Assign multiple permissions to this role
     */
    public function assignPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->assignPermission($permission);
        }
    }

    /**
     * Remove a permission from this role
     */
    public function revokePermission($permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->first();
        }

        if ($permission) {
            $this->permissions()->detach($permission->id);
        }
    }

    /**
     * Sync permissions - remove all and assign new ones
     */
    public function syncPermissions(array $permissions): void
    {
        $permissionIds = [];

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $perm = Permission::where('name', $permission)->first();
                if ($perm) {
                    $permissionIds[] = $perm->id;
                }
            } elseif ($permission instanceof Permission) {
                $permissionIds[] = $permission->id;
            } elseif (is_int($permission)) {
                $permissionIds[] = $permission;
            }
        }

        $this->permissions()->sync($permissionIds);
    }

    /**
     * Get permission names as array
     */
    public function getPermissionNames(): array
    {
        return $this->permissions()->pluck('name')->toArray();
    }

    // ==================== ENTITY TYPE RESTRICTIONS ====================

    /**
     * Get the entity types this role is restricted to
     * If empty, role applies to all entity types
     */
    public function entityTypes()
    {
        return $this->hasMany(RoleEntityType::class);
    }

    /**
     * Check if role applies to a specific entity type
     * Returns true if no restrictions (applies to all) or if type is in the list
     */
    public function appliesToEntityType(string $entityType): bool
    {
        $restrictions = $this->entityTypes()->pluck('entity_type')->toArray();

        // No restrictions means applies to all
        if (empty($restrictions)) {
            return true;
        }

        return in_array($entityType, $restrictions);
    }

    /**
     * Get the entity types this role applies to
     * Returns all types if no restrictions
     */
    public function getApplicableEntityTypes(): array
    {
        $restrictions = $this->entityTypes()->pluck('entity_type')->toArray();

        if (empty($restrictions)) {
            return [
                Employee::ENTITY_TYPE_BRANCH,
                Employee::ENTITY_TYPE_WORKSHOP,
                Employee::ENTITY_TYPE_FACTORY,
            ];
        }

        return $restrictions;
    }

    /**
     * Set the entity types this role is restricted to
     * Pass empty array to remove restrictions (apply to all)
     */
    public function setEntityTypes(array $entityTypes): void
    {
        // Remove existing restrictions
        $this->entityTypes()->delete();

        // Add new restrictions
        foreach ($entityTypes as $type) {
            $this->entityTypes()->create(['entity_type' => $type]);
        }
    }

    /**
     * Check if role is universal (no entity type restrictions)
     */
    public function isUniversal(): bool
    {
        return $this->entityTypes()->count() === 0;
    }
}
