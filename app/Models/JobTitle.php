<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class JobTitle extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
        'department_id',
        'level',
        'min_salary',
        'max_salary',
        'is_active',
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Level constants (enum values)
    public const LEVEL_MASTER_MANAGER = 'master_manager';
    public const LEVEL_BRANCHES_MANAGER = 'branches_manager';
    public const LEVEL_BRANCH_MANAGER = 'branch_manager';
    public const LEVEL_EMPLOYEE = 'employee';

    public const LEVELS = [
        self::LEVEL_MASTER_MANAGER => 'Master Manager',
        self::LEVEL_BRANCHES_MANAGER => 'Branches Manager',
        self::LEVEL_BRANCH_MANAGER => 'Branch Manager',
        self::LEVEL_EMPLOYEE => 'Employee',
    ];

    // Level hierarchy (lower number = higher rank)
    public const LEVEL_HIERARCHY = [
        self::LEVEL_MASTER_MANAGER => 1,
        self::LEVEL_BRANCHES_MANAGER => 2,
        self::LEVEL_BRANCH_MANAGER => 3,
        self::LEVEL_EMPLOYEE => 4,
    ];

    /**
     * Department this job title belongs to
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Employees with this job title
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Roles assigned to this job title
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'job_title_role')
                    ->withTimestamps();
    }

    /**
     * Get all permissions for this job title through its roles
     */
    public function getPermissions(): array
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Check if job title has a specific permission through its roles
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->exists();
    }

    /**
     * Assign a role to this job title
     */
    public function assignRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }
        
        if (!$this->roles()->where('roles.id', $role->id)->exists()) {
            $this->roles()->attach($role->id);
        }
    }

    /**
     * Remove a role from this job title
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
     * Sync roles for this job title
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

    /**
     * Scope for active job titles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by level
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for master managers
     */
    public function scopeMasterManagers($query)
    {
        return $query->where('level', self::LEVEL_MASTER_MANAGER);
    }

    /**
     * Scope for branches managers
     */
    public function scopeBranchesManagers($query)
    {
        return $query->where('level', self::LEVEL_BRANCHES_MANAGER);
    }

    /**
     * Scope for branch managers
     */
    public function scopeBranchManagers($query)
    {
        return $query->where('level', self::LEVEL_BRANCH_MANAGER);
    }

    /**
     * Scope for managers (any management level)
     */
    public function scopeManagers($query)
    {
        return $query->whereIn('level', [
            self::LEVEL_MASTER_MANAGER,
            self::LEVEL_BRANCHES_MANAGER,
            self::LEVEL_BRANCH_MANAGER,
        ]);
    }

    /**
     * Get level label
     */
    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? 'Unknown';
    }

    /**
     * Get level hierarchy number (lower = higher rank)
     */
    public function getLevelHierarchyAttribute(): int
    {
        return self::LEVEL_HIERARCHY[$this->level] ?? 999;
    }

    /**
     * Check if this is a management level position
     */
    public function getIsManagementAttribute(): bool
    {
        return in_array($this->level, [
            self::LEVEL_MASTER_MANAGER,
            self::LEVEL_BRANCHES_MANAGER,
            self::LEVEL_BRANCH_MANAGER,
        ]);
    }

    /**
     * Get salary range as formatted string
     */
    public function getSalaryRangeAttribute(): ?string
    {
        if ($this->min_salary && $this->max_salary) {
            return number_format($this->min_salary, 2) . ' - ' . number_format($this->max_salary, 2);
        }
        return null;
    }
}


