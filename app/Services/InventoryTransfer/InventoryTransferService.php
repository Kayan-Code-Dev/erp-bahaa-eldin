<?php

namespace App\Services\InventoryTransfer;

use App\Models\Branch;
use App\Models\BranchManager;
use App\Models\Category;
use App\Models\EmployeeLogin;
use App\Models\InventoryTransfer;
use App\Models\Inventory;
use App\Services\Inventories\InventoryService;
use Exception;
use Illuminate\Support\Facades\DB;

class InventoryTransferService
{

    public function getTransfersForUser($branch = null, $branchManager = null, $employeeLogin = null, $perPage = 10)
    {
        $query = InventoryTransfer::with(['inventory', 'fromBranch', 'toBranch', 'requester', 'approver'])->orderBy('created_at', 'desc');
        if ($employeeLogin) {
            $query->where(function ($q) use ($employeeLogin) {
                $q->where('from_branch_id', $employeeLogin->employee->branch_id)
                    ->orWhere('to_branch_id', $employeeLogin->employee->branch_id);
            });
        }
        // 🔹 مدير الفرع: يعرض عمليات النقل الخاصة بفروعه التي يديرها
        if ($branchManager) {
            $branchIds = $branchManager->manger->pluck('id');
            $query->where(function ($q) use ($branchIds) {
                $q->whereIn('from_branch_id', $branchIds)
                    ->orWhereIn('to_branch_id', $branchIds);
            });
        }
        // 🔹 الفرع الرئيسي: يعرض عمليات النقل الصادرة أو الواردة له
        if ($branch) {
            $query->where(function ($q) use ($branch) {
                $q->where('from_branch_id', $branch->id)
                    ->orWhere('to_branch_id', $branch->id);
            });
        }
        // 🔹 تنفيذ الاستعلام مع pagination
        $inventoryTransfers = $query->paginate($perPage);
        // 🔹 تنسيق البيانات قبل الإرجاع
        $mapped = $inventoryTransfers->getCollection()->map(fn($transfer) => $this->formatInventoryTransfer($transfer))->values();
        return [
            'data' => $mapped,
            'current_page' => $inventoryTransfers->currentPage(),
            'next_page_url' => $inventoryTransfers->nextPageUrl(),
            'prev_page_url' => $inventoryTransfers->previousPageUrl(),
            'total' => $inventoryTransfers->total(),
        ];
    }

    public function getMyBranches(BranchManager $branchManager)
    {
        $branches = Branch::query()->where('branch_manager_id', $branchManager->id)->get();
        return $branches->map(function ($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name
            ];
        });
    }


    public function getCategories($branchId)
    {
        $categories = Category::where('branch_id', '=', $branchId)->where('active', true)->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        });
        return $categories;
    }

    public function getSubCategoriesByCategory(Category $category)
    {

        $subCategories = $category->subCategories()->where('active', true)->get()->map(function ($subCategory) {
            return [
                'id' => $subCategory->id,
                'name' => $subCategory->name,
            ];
        });
        return $subCategories;
    }

    public function createTransfer(array $data): InventoryTransfer
    {
        $inventory = Inventory::findOrFail($data['inventory_id']);
        if ($inventory->quantity < $data['quantity']) {
            throw new Exception('الكمية المطلوبة أكبر من المخزون المتاح');
        }
        $transfer = InventoryTransfer::create($data);
        if ($data['requested_by_type'] === BranchManager::class) {
            $this->approveTransfer($transfer, auth('branchManager-api')->user());
        }
        return $transfer;
    }



    public function approveTransfer(InventoryTransfer $transfer, $user)
    {
        if ($transfer->status !== 'pending') {
            throw new Exception('تمت الموافقة أو الرفض مسبقًا');
        }
        if ($transfer->requested_by_type === BranchManager::class) {

            DB::transaction(function () use ($transfer, $user) {
                $this->executeTransfer($transfer);
                $transfer->update([
                    'status' => 'approved',
                    'approved_by_id' => $user->id,
                    'approved_by_type' => get_class($user),
                    'arrival_date' => now(),
                ]);
            });
            return;
        }

        if (!$this->canApprove($transfer, $user)) {
            throw new Exception('ليس لديك صلاحية الموافقة على هذا الطلب');
        }
        DB::transaction(function () use ($transfer, $user) {
            $this->executeTransfer($transfer);
            $transfer->update([
                'status' => 'approved',
                'approved_by_id' => $user->id,
                'approved_by_type' => get_class($user),
                'arrival_date' => now(),
            ]);
        });
    }

    public function rejectTransfer(InventoryTransfer $transfer, $user)
    {
        if ($transfer->status !== 'pending') {
            throw new Exception('تمت الموافقة أو الرفض مسبقًا');
        }

        if (!$this->canApprove($transfer, $user)) {
            throw new Exception('ليس لديك صلاحية رفض هذا الطلب');
        }
        $transfer->update([
            'status' => 'rejected',
            'approved_by_id' => $user->id,
            'approved_by_type' => get_class($user),
            'arrival_date' => now(),

        ]);
    }

    protected function canApprove(InventoryTransfer $transfer, $user)
    {
        $requestedByType = $transfer->requested_by_type;

        if ($requestedByType === EmployeeLogin::class) {
            return $user instanceof Branch || $user instanceof BranchManager;
        }

        if ($requestedByType === Branch::class) {
            return $user instanceof BranchManager;
        }
        return false;
    }

    protected function executeTransfer(InventoryTransfer $transfer)
    {
        $inventoryFrom = Inventory::where('branch_id', $transfer->from_branch_id)->where('subCategories_id', $transfer->inventory->subCategories_id)->where('type', $transfer->inventory->type)->first();
        if (!$inventoryFrom) {
            throw new \Exception('المخزون المصدر غير موجود في هذا الفرع.');
        }
        if ($inventoryFrom->quantity < $transfer->quantity) {
            throw new \Exception('الكمية غير كافية في المخزون المصدر.');
        }
        $inventoryFrom->decrement('quantity', $transfer->quantity);

        $inventoryTo = Inventory::firstOrCreate([
            'subCategories_id' => $transfer->inventory->subCategories_id,
            'branch_id' => $transfer->to_branch_id,
            'type' => $transfer->inventory->type,
        ], [
            'name' => $transfer->inventory->name,
            'quantity' => 0,
            'price' => $transfer->inventory->price,
            'code' => InventoryService::generateInventoryCode('RAW'),

        ]);
        $inventoryTo->increment('quantity', $transfer->quantity);
    }



    public function formatInventoryTransfer(InventoryTransfer $transfer): array
    {
        return [
            'uuid' => $transfer->uuid,
            'product_name' => $transfer->inventory?->name ?? null,
            'quantity' => $transfer->quantity ?? 0,
            'from_branch_name' => $transfer->fromBranch?->name ?? null,
            'to_branch_name' => $transfer->toBranch?->name ?? null,
            'transfer_date' => $transfer->created_at ? $transfer->created_at->format('d-m-Y') : null,
            'arrival_date' => $transfer->arrival_date ? $transfer->arrival_date->format('d-m-Y') : null,
            'status' => match ($transfer->status) {
                'pending'   => 'قيد الانتظار',
                'approved'  => 'تم القبول',
                'rejected'  => 'تم الرفض',
                'arrived'   => 'تم الوصول',
                default     => 'قيد الانتظار',
            },
        ];
    }
}
