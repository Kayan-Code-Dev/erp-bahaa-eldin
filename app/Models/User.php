<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\LogsActivity;
use App\Models\FactoryUser;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    /**
     * Super admin email - this user has ALL permissions automatically
     */
    public const SUPER_ADMIN_EMAIL = 'admin@admin.com';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Roles assigned to the user (many-to-many via role_user)
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Payments created by this user
     */
    public function payments()
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    /**
     * Order returns created by this user
     */
    public function orderReturns()
    {
        return $this->hasMany(OrderReturn::class, 'created_by');
    }

    /**
     * Custody returns returned by this user
     */
    public function custodyReturns()
    {
        return $this->hasMany(CustodyReturn::class, 'returned_by');
    }

    /**
     * Cloth history records created by this user
     */
    public function clothHistory()
    {
        return $this->hasMany(ClothHistory::class, 'user_id');
    }

    /**
     * Employee profile for this user (1:1 relationship)
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Check if user has an employee profile
     */
public function hasEmployeeProfile(): bool
    {
        return $this->employee()->exists();
    }

    /**
     * Factory user profile (if user belongs to a factory)
     */
    public function factoryUser()
    {
        return $this->hasOne(FactoryUser::class);
    }

    /**
     * Get the factory this user belongs to (through FactoryUser)
     */
    public function assignedFactory()
    {
        return $this->hasOneThrough(Factory::class, FactoryUser::class, 'user_id', 'id', 'id', 'factory_id');
    }

    /**
     * Check if user is a factory user
     */
    public function isFactoryUser(): bool
    {
        return $this->factoryUser()->exists();
    }

    /**
     * Get the factory ID for this user
     */
    public function getFactoryId(): ?int
    {
        // Eager load factoryUser if not already loaded
        if (!$this->relationLoaded('factoryUser')) {
            $this->load('factoryUser');
        }
        
        // If relationship is loaded, use it
        if ($this->factoryUser && $this->factoryUser->is_active) {
            return $this->factoryUser->factory_id;
        }
        
        // Otherwise, query directly
        $factoryUser = FactoryUser::where('user_id', $this->id)
            ->where('is_active', true)
            ->first();
        return $factoryUser?->factory_id;
    }

    /**
     * Activity logs for this user
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Transfer actions performed by this user
     */
    public function transferActions()
    {
        return $this->hasMany(TransferAction::class, 'user_id');
    }

    /**
     * Check if user is the super admin (admin@admin.com)
     * Super admin has ALL permissions automatically
     */
    public function isSuperAdmin(): bool
    {
        return strtolower($this->email) === strtolower(self::SUPER_ADMIN_EMAIL);
    }

    /**
     * Check if user has a specific permission
     * Super admin (admin@admin.com) has all permissions automatically
     * 
     * Permissions are merged from:
     * 1. User's direct roles (via role_user)
     * 2. JobTitle's roles (via Employee -> JobTitle -> job_title_role)
     * 
     * If ANY source grants the permission, the user has it (union).
     */
    public function hasPermission(string $permissionName): bool
    {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check permissions through user's direct roles
        $hasDirectPermission = $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();

        if ($hasDirectPermission) {
            return true;
        }

        // Check permissions through JobTitle's roles (if user has an employee profile)
        if ($this->hasEmployeeProfile()) {
            $employee = $this->employee;
            if ($employee && $employee->jobTitle) {
                return $employee->jobTitle->hasPermission($permissionName);
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given permissions
     * Checks both direct roles and JobTitle roles.
     */
    public function hasAnyPermission(array $permissionNames): bool
    {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check direct roles
        $hasDirect = $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionNames) {
                $query->whereIn('name', $permissionNames);
            })
            ->exists();

        if ($hasDirect) {
            return true;
        }

        // Check JobTitle roles
        if ($this->hasEmployeeProfile()) {
            $employee = $this->employee;
            if ($employee && $employee->jobTitle) {
                return $employee->jobTitle->roles()
                    ->whereHas('permissions', function ($query) use ($permissionNames) {
                        $query->whereIn('name', $permissionNames);
                    })
                    ->exists();
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(array $permissionNames): bool
    {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissionNames as $permissionName) {
            if (!$this->hasPermission($permissionName)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all permission names for this user
     * Super admin gets ALL permissions in the system
     * 
     * Permissions are merged from:
     * 1. User's direct roles (via role_user)
     * 2. JobTitle's roles (via Employee -> JobTitle -> job_title_role)
     */
    public function getAllPermissions(): array
    {
        // Super admin gets all permissions
        if ($this->isSuperAdmin()) {
            return Permission::pluck('name')->toArray();
        }

        // Get permissions from user's direct roles
        $directPermissions = $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name');

        // Get permissions from JobTitle's roles (if user has an employee profile)
        $jobTitlePermissions = collect();
        if ($this->hasEmployeeProfile()) {
            $employee = $this->employee;
            if ($employee && $employee->jobTitle) {
                $jobTitlePermissions = collect($employee->jobTitle->getPermissions());
            }
        }

        // Merge and return unique permissions
        return $directPermissions
            ->merge($jobTitlePermissions)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Assign a role to this user
     */
    public function assignRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }
        
        if (!$this->hasRole($role->name)) {
            $this->roles()->attach($role->id);
        }
    }

    /**
     * Remove a role from this user
     */
    public function removeRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->first();
        }
        
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    /**
     * Sync roles - remove all and assign new ones
     */
    public function syncRoles(array $roles): void
    {
        $roleIds = [];
        
        foreach ($roles as $role) {
            if (is_string($role)) {
                $r = Role::where('name', $role)->first();
                if ($r) {
                    $roleIds[] = $r->id;
                }
            } elseif ($role instanceof Role) {
                $roleIds[] = $role->id;
            } elseif (is_int($role)) {
                $roleIds[] = $role;
            }
        }
        
        $this->roles()->sync($roleIds);
    }

    // ==================== ENTITY ACCESS METHODS ====================

    /**
     * Check if user can access a specific entity
     *
     * @param string $entityType The entity type (branch, workshop, factory)
     * @param int $entityId The entity ID
     * @param string|null $permission Optional permission to check
     * @return bool
     */
    public function canAccessEntity(string $entityType, int $entityId, ?string $permission = null): bool
    {
        $service = app(\App\Services\EntityAccessService::class);
        return $service->canAccessEntity($this, $entityType, $entityId, $permission);
    }

    /**
     * Get all accessible entity IDs for a specific type
     *
     * @param string $entityType
     * @return array|null Array of entity IDs, or null if all are accessible
     */
    public function getAccessibleEntityIds(string $entityType): ?array
    {
        $service = app(\App\Services\EntityAccessService::class);
        return $service->getAccessibleEntityIds($this, $entityType);
    }

    /**
     * Get all accessible inventory IDs
     *
     * @return array|null Array of inventory IDs, or null if all are accessible
     */
    public function getAccessibleInventoryIds(): ?array
    {
        $service = app(\App\Services\EntityAccessService::class);
        return $service->getAccessibleInventoryIds($this);
    }

    /**
     * Check if user can access a specific inventory
     *
     * @param int $inventoryId
     * @return bool
     */
    public function canAccessInventory(int $inventoryId): bool
    {
        $service = app(\App\Services\EntityAccessService::class);
        return $service->canAccessInventory($this, $inventoryId);
    }

    /**
     * Check if user has full access (Master Manager or Super Admin)
     *
     * @return bool
     */
    public function hasFullAccess(): bool
    {
        $service = app(\App\Services\EntityAccessService::class);
        return $service->hasFullAccess($this);
    }

    /**
     * Get the user's job title level
     *
     * @return string|null
     */
    public function getLevel(): ?string
    {
        $service = app(\App\Services\EntityAccessService::class);
        return $service->getUserLevel($this);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
