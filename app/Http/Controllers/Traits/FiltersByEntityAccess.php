<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Services\EntityAccessService;
use App\Models\Employee;

/**
 * Trait FiltersByEntityAccess
 * 
 * Provides methods for filtering query results based on entity access.
 * Use this trait in controllers that need to filter data by accessible entities.
 */
trait FiltersByEntityAccess
{
    /**
     * Get the EntityAccessService instance
     */
    protected function getEntityAccessService(): EntityAccessService
    {
        return app(EntityAccessService::class);
    }

    /**
     * Filter a query by accessible inventories
     * 
     * @param Builder $query The query builder
     * @param Request $request The HTTP request
     * @param string $inventoryColumn The column name for inventory_id
     * @return Builder
     */
    protected function filterByAccessibleInventories(Builder $query, Request $request, string $inventoryColumn = 'inventory_id'): Builder
    {
        $user = $request->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0'); // No results
        }

        $accessibleIds = $this->getEntityAccessService()->getAccessibleInventoryIds($user);
        
        // null means all are accessible
        if ($accessibleIds === null) {
            return $query;
        }

        // Empty array means no access
        if (empty($accessibleIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($inventoryColumn, $accessibleIds);
    }

    /**
     * Filter a query by accessible branches
     * 
     * @param Builder $query The query builder
     * @param Request $request The HTTP request
     * @param string $branchColumn The column name for branch_id
     * @return Builder
     */
    protected function filterByAccessibleBranches(Builder $query, Request $request, string $branchColumn = 'branch_id'): Builder
    {
        $user = $request->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $accessibleIds = $this->getEntityAccessService()->getAccessibleEntityIds($user, Employee::ENTITY_TYPE_BRANCH);
        
        if ($accessibleIds === null) {
            return $query;
        }

        if (empty($accessibleIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($branchColumn, $accessibleIds);
    }

    /**
     * Filter a query by accessible workshops
     * 
     * @param Builder $query The query builder
     * @param Request $request The HTTP request
     * @param string $workshopColumn The column name for workshop_id
     * @return Builder
     */
    protected function filterByAccessibleWorkshops(Builder $query, Request $request, string $workshopColumn = 'workshop_id'): Builder
    {
        $user = $request->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $accessibleIds = $this->getEntityAccessService()->getAccessibleEntityIds($user, Employee::ENTITY_TYPE_WORKSHOP);
        
        if ($accessibleIds === null) {
            return $query;
        }

        if (empty($accessibleIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($workshopColumn, $accessibleIds);
    }

    /**
     * Filter a query by accessible factories
     * 
     * @param Builder $query The query builder
     * @param Request $request The HTTP request
     * @param string $factoryColumn The column name for factory_id
     * @return Builder
     */
    protected function filterByAccessibleFactories(Builder $query, Request $request, string $factoryColumn = 'factory_id'): Builder
    {
        $user = $request->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $accessibleIds = $this->getEntityAccessService()->getAccessibleEntityIds($user, Employee::ENTITY_TYPE_FACTORY);
        
        if ($accessibleIds === null) {
            return $query;
        }

        if (empty($accessibleIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($factoryColumn, $accessibleIds);
    }

    /**
     * Check if user can access a specific inventory
     * 
     * @param Request $request
     * @param int $inventoryId
     * @return bool
     */
    protected function canAccessInventory(Request $request, int $inventoryId): bool
    {
        $user = $request->user();
        
        if (!$user) {
            return false;
        }

        return $this->getEntityAccessService()->canAccessInventory($user, $inventoryId);
    }

    /**
     * Check if user can access a specific entity
     * 
     * @param Request $request
     * @param string $entityType
     * @param int $entityId
     * @return bool
     */
    protected function canAccessEntity(Request $request, string $entityType, int $entityId): bool
    {
        $user = $request->user();
        
        if (!$user) {
            return false;
        }

        return $this->getEntityAccessService()->canAccessEntity($user, $entityType, $entityId);
    }

    /**
     * Return a 403 response for entity access denial
     * 
     * @param string $entityType
     * @param int|null $entityId
     * @return \Illuminate\Http\JsonResponse
     */
    protected function entityAccessDenied(string $entityType, ?int $entityId = null)
    {
        return response()->json([
            'message' => 'Forbidden. You do not have access to this entity.',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ], 403);
    }
}

