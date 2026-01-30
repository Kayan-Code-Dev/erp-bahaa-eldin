<?php

namespace App\Services\Inventories;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use Exception;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * 🔹 جلب المخزون للفرع الحالي مع حالة المخزون
     */
    public function index(int $perPage = 10): array
    {
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $branchId = $branchUser ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $inventories = Inventory::where('branch_id', $branchId)->with('subCategory')->paginate($perPage);
        $mapped = $inventories->getCollection()->map(fn($inventory) => $this->formatInventory($inventory));
        return [
            'data' => $mapped,
            'current_page' => $inventories->currentPage(),
            'next_page_url' => $inventories->nextPageUrl(),
            'prev_page_url' => $inventories->previousPageUrl(),
            'total' => $inventories->total(),
        ];
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

    public function getBranches($branch)
    {

        $branches = Branch::where('branch_manager_id', $branch->branch_manager_id)->where('status', '=', 'active')->where('id', '!=', $branch->id)->get(['id', 'name']);
        return $branches;
    }

    /**
     * 🔹 إنشاء مخزون جديد
     */
    public function createInventory(array $data): ?Inventory
    {
        try {
            return Inventory::create([
                'branch_id'   => $data['branch_id'],
                'subCategories_id' => $data['subCategories_id'] ?? null,
                'name'        => $data['name'],
                'code'        => $this->generateInventoryCode('RAW'),
                'price'       => $data['price'] ?? 0,
                'type'        => $data['type'],
                'notes'       => $data['notes'] ?? null,
                'quantity'    => $data['quantity'] ?? 0,
            ]);
        } catch (Exception $e) {
            Log::error('Inventory creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 🔹 تعديل مخزون موجود
     */
    public function updateInventory(string $id, array $data): ?Inventory
    {
        $inventory = Inventory::find($id);
        if (!$inventory) return null;

        $inventory->update($data);
        return $inventory;
    }

    /**
     * 🔹 حذف مخزون
     */
    public function deleteInventory(string $id): bool
    {
        $inventory = Inventory::find($id);
        if (!$inventory) return false;

        $inventory->delete();
        return true;
    }

    public function indexBranchManager($perPage = 10)
    {
        $branchManager = auth('branchManager-api')->user();
        $branchIds = $branchManager->manger->pluck('id')->toArray();
        $inventories = Inventory::whereIn('branch_id', $branchIds)->with(['subCategory', 'branch'])->paginate($perPage);
        $mapped = $inventories->getCollection()->map(fn($inventory) => $this->formatInventory($inventory, true));
        return [
            'data' => $mapped,
            'current_page' => $inventories->currentPage(),
            'next_page_url' => $inventories->nextPageUrl(),
            'prev_page_url' => $inventories->previousPageUrl(),
            'total' => $inventories->total(),
        ];
    }


    public function formatInventory(Inventory $inventory, bool $showBranch = false): array
    {
        if ($inventory->quantity >= 0 && $inventory->quantity <= 5) {
            $status = 'منخفضة';
        } elseif ($inventory->quantity > 5 && $inventory->quantity <= 10) {
            $status = 'كافية';
        } else {
            $status = 'مرتفع';
        }

        $data = [
            'id' => $inventory->id,
            'code' => $inventory->code ?? '',
            'name' => $inventory->name ?? '',
            'price' => $inventory->price ?? '',
            'type' => $inventory->type ?? '',
            'category_id' => $inventory->subCategory?->category?->id ?? '',
            'category_name' => $inventory->subCategory?->category?->name ?? '',
            'sub_category_name' => $inventory->subCategory?->name ?? '',
            'sub_category_id' => $inventory->subCategory?->id ?? '',
            'quantity' => $inventory->quantity ?? 0,
            'updated_at' => $inventory->updated_at ? $inventory->updated_at->format('d-m-Y') : '',
            'status' => $status,
        ];

        if ($showBranch) {
            $data['branch_name'] = $inventory->branch?->name ?? '';
        }

        return $data;
    }


    public static function generateInventoryCode(string $prefix): string
    {
        $lastCode = Inventory::where('code', 'like', $prefix . '-%')->orderBy('code', 'desc')->pluck('code')->first();
        if ($lastCode) {
            $number = (int) substr($lastCode, strpos($lastCode, '-') + 1);
            $number++;
        } else {
            $number = 1;
        }
        return $prefix . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
