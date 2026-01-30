<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\EntityAccessService;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Transfer;

/**
 * CheckEntityAccess Middleware
 * 
 * Checks if the authenticated user has access to the entity being requested.
 * This middleware should be used after auth:sanctum and permission middleware.
 * 
 * Usage in routes:
 *   Route::middleware(['auth:sanctum', 'permission:orders.view', 'entity.access:inventory'])->...
 *   Route::middleware(['auth:sanctum', 'permission:transfers.create', 'entity.access:source'])->...
 *   Route::middleware(['auth:sanctum', 'permission:transfers.approve', 'entity.access:destination'])->...
 * 
 * Entity source types:
 *   - inventory: Uses inventory_id from request or route parameter
 *   - branch: Uses branch_id from request or route parameter
 *   - workshop: Uses workshop_id from request or route parameter
 *   - factory: Uses factory_id from request or route parameter
 *   - source: For transfers - uses source inventory to determine entity
 *   - destination: For transfers - uses destination inventory to determine entity
 *   - order: Uses order's inventory to determine entity
 */
class CheckEntityAccess
{
    protected EntityAccessService $entityAccessService;

    public function __construct(EntityAccessService $entityAccessService)
    {
        $this->entityAccessService = $entityAccessService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $entitySource  The source of the entity ID (inventory, branch, workshop, factory, source, destination, order)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $entitySource): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super admin bypasses all entity checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Master Manager bypasses all entity checks
        if ($this->entityAccessService->hasFullAccess($user)) {
            return $next($request);
        }

        // Determine entity type and ID based on source
        $entityInfo = $this->resolveEntity($request, $entitySource);

        if ($entityInfo === null) {
            // Could not determine entity - allow (will be caught by other validation)
            return $next($request);
        }

        // Check if user can access this entity
        if (!$this->entityAccessService->canAccessEntity($user, $entityInfo['type'], $entityInfo['id'])) {
            return response()->json([
                'message' => 'Forbidden. You do not have access to this entity.',
                'entity_type' => $entityInfo['type'],
                'entity_id' => $entityInfo['id'],
            ], 403);
        }

        return $next($request);
    }

    /**
     * Resolve entity type and ID from the request based on entity source
     * 
     * @param Request $request
     * @param string $entitySource
     * @return array|null ['type' => string, 'id' => int]
     */
    protected function resolveEntity(Request $request, string $entitySource): ?array
    {
        switch ($entitySource) {
            case 'inventory':
                return $this->resolveFromInventory($request);

            case 'branch':
                $branchId = $request->input('branch_id') ?? $request->route('branch') ?? $request->route('id');
                if ($branchId) {
                    return ['type' => Employee::ENTITY_TYPE_BRANCH, 'id' => (int) $branchId];
                }
                break;

            case 'workshop':
                $workshopId = $request->input('workshop_id') ?? $request->route('workshop') ?? $request->route('id');
                if ($workshopId) {
                    return ['type' => Employee::ENTITY_TYPE_WORKSHOP, 'id' => (int) $workshopId];
                }
                break;

            case 'factory':
                $factoryId = $request->input('factory_id') ?? $request->route('factory') ?? $request->route('id');
                if ($factoryId) {
                    return ['type' => Employee::ENTITY_TYPE_FACTORY, 'id' => (int) $factoryId];
                }
                break;

            case 'source':
                return $this->resolveFromTransferSource($request);

            case 'destination':
                return $this->resolveFromTransferDestination($request);

            case 'order':
                return $this->resolveFromOrder($request);
        }

        return null;
    }

    /**
     * Resolve entity from inventory_id in request
     */
    protected function resolveFromInventory(Request $request): ?array
    {
        $inventoryId = $request->input('inventory_id') ?? $request->route('inventory');
        
        if (!$inventoryId) {
            return null;
        }

        $inventory = Inventory::find($inventoryId);
        if (!$inventory) {
            return null;
        }

        return $this->entityAccessService->resolveEntityFromInventory($inventory);
    }

    /**
     * Resolve entity from transfer source
     */
    protected function resolveFromTransferSource(Request $request): ?array
    {
        // For existing transfer
        $transferId = $request->route('transfer') ?? $request->route('id');
        if ($transferId) {
            $transfer = Transfer::find($transferId);
            if ($transfer && $transfer->sourceInventory) {
                return $this->entityAccessService->resolveEntityFromInventory($transfer->sourceInventory);
            }
        }

        // For new transfer
        $sourceInventoryId = $request->input('source_inventory_id');
        if ($sourceInventoryId) {
            $inventory = Inventory::find($sourceInventoryId);
            if ($inventory) {
                return $this->entityAccessService->resolveEntityFromInventory($inventory);
            }
        }

        return null;
    }

    /**
     * Resolve entity from transfer destination
     */
    protected function resolveFromTransferDestination(Request $request): ?array
    {
        // For existing transfer
        $transferId = $request->route('transfer') ?? $request->route('id');
        if ($transferId) {
            $transfer = Transfer::find($transferId);
            if ($transfer && $transfer->destinationInventory) {
                return $this->entityAccessService->resolveEntityFromInventory($transfer->destinationInventory);
            }
        }

        // For new transfer
        $destInventoryId = $request->input('destination_inventory_id');
        if ($destInventoryId) {
            $inventory = Inventory::find($destInventoryId);
            if ($inventory) {
                return $this->entityAccessService->resolveEntityFromInventory($inventory);
            }
        }

        return null;
    }

    /**
     * Resolve entity from order
     */
    protected function resolveFromOrder(Request $request): ?array
    {
        $orderId = $request->route('order') ?? $request->route('id');
        
        if ($orderId) {
            $order = Order::find($orderId);
            if ($order && $order->inventory) {
                return $this->entityAccessService->resolveEntityFromInventory($order->inventory);
            }
        }

        // For new order
        $inventoryId = $request->input('inventory_id');
        if ($inventoryId) {
            $inventory = Inventory::find($inventoryId);
            if ($inventory) {
                return $this->entityAccessService->resolveEntityFromInventory($inventory);
            }
        }

        return null;
    }
}

