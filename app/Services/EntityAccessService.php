<?php

namespace App\Services;

use App\Models\User;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

/**
 * EntityAccessService
 * 
 * Centralized service for entity-scoped access control.
 * Handles checking if a user can access a specific entity based on:
 * - Job title level (master_manager, branches_manager, branch_manager, employee)
 * - Entity assignments (employee_entity table)
 * - Role entity type restrictions
 */
class EntityAccessService
{
    /**
     * Check if user can access a specific entity
     * 
     * @param User $user The user to check
     * @param string $entityType The entity type (branch, workshop, factory)
     * @param int $entityId The entity ID
     * @param string|null $permission Optional permission to check (considers role entity type restrictions)
     * @return bool
     */
    public function canAccessEntity(User $user, string $entityType, int $entityId, ?string $permission = null): bool
    {
        // Super admin has access to everything
        if ($user->isSuperAdmin()) {
            return true;
        }

        $employee = $user->employee;
        if (!$employee || !$employee->jobTitle) {
            return false;
        }

        $level = $employee->jobTitle->level;

        // Master Manager has access to ALL entities
        if ($level === JobTitle::LEVEL_MASTER_MANAGER) {
            return true;
        }

        // Branches Manager has access to ALL branches and workshops
        if ($level === JobTitle::LEVEL_BRANCHES_MANAGER) {
            if (in_array($entityType, [Employee::ENTITY_TYPE_BRANCH, Employee::ENTITY_TYPE_WORKSHOP])) {
                return true;
            }
            // For factories, check assignment
            return $employee->isAssignedTo($entityType, $entityId);
        }

        // Branch Manager and Employee: check entity assignment
        if (in_array($level, [JobTitle::LEVEL_BRANCH_MANAGER, JobTitle::LEVEL_EMPLOYEE])) {
            // Check direct entity assignment
            if ($employee->isAssignedTo($entityType, $entityId)) {
                // If permission is specified, check role entity type restrictions
                if ($permission !== null) {
                    return $this->hasPermissionForEntityType($user, $permission, $entityType);
                }
                return true;
            }
            
            // For workshops, also check if assigned to the parent branch
            if ($entityType === Employee::ENTITY_TYPE_WORKSHOP) {
                $workshop = Workshop::find($entityId);
                if ($workshop && $workshop->branch_id) {
                    if ($employee->isAssignedTo(Employee::ENTITY_TYPE_BRANCH, $workshop->branch_id)) {
                        if ($permission !== null) {
                            return $this->hasPermissionForEntityType($user, $permission, $entityType);
                        }
                        return true;
                    }
                }
            }
            
            return false;
        }

        return false;
    }

    /**
     * Check if user has a permission that applies to a specific entity type
     * 
     * @param User $user
     * @param string $permission
     * @param string $entityType
     * @return bool
     */
    public function hasPermissionForEntityType(User $user, string $permission, string $entityType): bool
    {
        // Super admin has all permissions
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check user's direct roles
        $hasPermission = $user->roles()
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('name', $permission);
            })
            ->where(function ($q) use ($entityType) {
                // Role applies to all entity types (no restrictions)
                $q->whereDoesntHave('entityTypes')
                    // OR role applies to this specific entity type
                    ->orWhereHas('entityTypes', function ($q2) use ($entityType) {
                        $q2->where('entity_type', $entityType);
                    });
            })
            ->exists();

        if ($hasPermission) {
            return true;
        }

        // Check job title's roles
        $employee = $user->employee;
        if ($employee && $employee->jobTitle) {
            return $employee->jobTitle->roles()
                ->whereHas('permissions', function ($q) use ($permission) {
                    $q->where('name', $permission);
                })
                ->where(function ($q) use ($entityType) {
                    $q->whereDoesntHave('entityTypes')
                        ->orWhereHas('entityTypes', function ($q2) use ($entityType) {
                            $q2->where('entity_type', $entityType);
                        });
                })
                ->exists();
        }

        return false;
    }

    /**
     * Get all entity IDs the user can access for a specific type
     * 
     * @param User $user
     * @param string $entityType
     * @return array Array of entity IDs, or null if all are accessible
     */
    public function getAccessibleEntityIds(User $user, string $entityType): ?array
    {
        // Super admin has access to all
        if ($user->isSuperAdmin()) {
            return null; // null means "all"
        }

        $employee = $user->employee;
        if (!$employee || !$employee->jobTitle) {
            return []; // No access
        }

        $level = $employee->jobTitle->level;

        // Master Manager has access to all entities
        if ($level === JobTitle::LEVEL_MASTER_MANAGER) {
            return null;
        }

        // Branches Manager has access to all branches and workshops
        if ($level === JobTitle::LEVEL_BRANCHES_MANAGER) {
            if ($entityType === Employee::ENTITY_TYPE_BRANCH) {
                return null; // All branches
            }
            if ($entityType === Employee::ENTITY_TYPE_WORKSHOP) {
                return null; // All workshops
            }
            // For factories, return assigned ones
            return $employee->assignedFactories()->pluck('id')->toArray();
        }

        // Branch Manager and Employee: return assigned entities
        $assignedIds = DB::table('employee_entity')
            ->where('employee_id', $employee->id)
            ->where('entity_type', $entityType)
            ->whereNull('unassigned_at')
            ->pluck('entity_id')
            ->toArray();

        // For workshops, also include workshops of assigned branches
        if ($entityType === Employee::ENTITY_TYPE_WORKSHOP) {
            $branchIds = DB::table('employee_entity')
                ->where('employee_id', $employee->id)
                ->where('entity_type', Employee::ENTITY_TYPE_BRANCH)
                ->whereNull('unassigned_at')
                ->pluck('entity_id')
                ->toArray();

            if (!empty($branchIds)) {
                $workshopIds = Workshop::whereIn('branch_id', $branchIds)->pluck('id')->toArray();
                $assignedIds = array_unique(array_merge($assignedIds, $workshopIds));
            }
        }

        return $assignedIds;
    }

    /**
     * Get all accessible inventory IDs for a user
     * Inventories are linked to branches or workshops
     * 
     * @param User $user
     * @return array|null Array of inventory IDs, or null if all are accessible
     */
    public function getAccessibleInventoryIds(User $user): ?array
    {
        // Super admin and Master Manager have access to all
        if ($user->isSuperAdmin()) {
            return null;
        }

        $employee = $user->employee;
        if (!$employee || !$employee->jobTitle) {
            return [];
        }

        if ($employee->jobTitle->level === JobTitle::LEVEL_MASTER_MANAGER) {
            return null;
        }

        // Branches Manager has access to all branch and workshop inventories
        if ($employee->jobTitle->level === JobTitle::LEVEL_BRANCHES_MANAGER) {
            return null;
        }

        // Get accessible branches and workshops
        $branchIds = $this->getAccessibleEntityIds($user, Employee::ENTITY_TYPE_BRANCH) ?? [];
        $workshopIds = $this->getAccessibleEntityIds($user, Employee::ENTITY_TYPE_WORKSHOP) ?? [];

        $inventoryIds = [];

        // Get inventories for accessible branches
        if (is_array($branchIds) && !empty($branchIds)) {
            $branchInventories = Inventory::where('inventoriable_type', 'branch')
                ->whereIn('inventoriable_id', $branchIds)
                ->pluck('id')
                ->toArray();
            $inventoryIds = array_merge($inventoryIds, $branchInventories);
        } elseif ($branchIds === null) {
            // All branches - get all branch inventories
            $branchInventories = Inventory::where('inventoriable_type', 'branch')
                ->pluck('id')
                ->toArray();
            $inventoryIds = array_merge($inventoryIds, $branchInventories);
        }

        // Get inventories for accessible workshops
        if (is_array($workshopIds) && !empty($workshopIds)) {
            $workshopInventories = Inventory::where('inventoriable_type', 'workshop')
                ->whereIn('inventoriable_id', $workshopIds)
                ->pluck('id')
                ->toArray();
            $inventoryIds = array_merge($inventoryIds, $workshopInventories);
        } elseif ($workshopIds === null) {
            // All workshops - get all workshop inventories
            $workshopInventories = Inventory::where('inventoriable_type', 'workshop')
                ->pluck('id')
                ->toArray();
            $inventoryIds = array_merge($inventoryIds, $workshopInventories);
        }

        return array_unique($inventoryIds);
    }

    /**
     * Check if user can access a specific inventory
     * 
     * @param User $user
     * @param int $inventoryId
     * @return bool
     */
    public function canAccessInventory(User $user, int $inventoryId): bool
    {
        $accessibleIds = $this->getAccessibleInventoryIds($user);
        
        // null means all are accessible
        if ($accessibleIds === null) {
            return true;
        }

        return in_array($inventoryId, $accessibleIds);
    }

    /**
     * Resolve entity type and ID from an inventory
     * 
     * @param Inventory $inventory
     * @return array ['type' => string, 'id' => int]|null
     */
    public function resolveEntityFromInventory(Inventory $inventory): ?array
    {
        if ($inventory->inventoriable_type === 'branch' || $inventory->inventoriable_type === Branch::class) {
            return [
                'type' => Employee::ENTITY_TYPE_BRANCH,
                'id' => $inventory->inventoriable_id,
            ];
        }

        if ($inventory->inventoriable_type === 'workshop' || $inventory->inventoriable_type === Workshop::class) {
            return [
                'type' => Employee::ENTITY_TYPE_WORKSHOP,
                'id' => $inventory->inventoriable_id,
            ];
        }

        return null;
    }

    /**
     * Get the user's job title level
     * 
     * @param User $user
     * @return string|null
     */
    public function getUserLevel(User $user): ?string
    {
        if ($user->isSuperAdmin()) {
            return JobTitle::LEVEL_MASTER_MANAGER;
        }

        $employee = $user->employee;
        if (!$employee || !$employee->jobTitle) {
            return null;
        }

        return $employee->jobTitle->level;
    }

    /**
     * Check if user has full access (Master Manager or Super Admin)
     * 
     * @param User $user
     * @return bool
     */
    public function hasFullAccess(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $level = $this->getUserLevel($user);
        return $level === JobTitle::LEVEL_MASTER_MANAGER;
    }
}

