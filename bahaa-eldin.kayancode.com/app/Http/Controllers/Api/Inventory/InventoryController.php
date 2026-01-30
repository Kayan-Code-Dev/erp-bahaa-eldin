<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Models\Category;
use App\Models\EmployeeLogin;
use App\Models\Inventory;
use App\Services\Inventories\InventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;
    protected ?Branch $branch;
    protected ?BranchManager $branchManager;
    protected ?EmployeeLogin $employeeLogin;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
        $this->branch = auth('branch-api')->user();
        $this->employeeLogin = auth('employee-api')->user();
        $this->branchManager = auth('branchManager-api')->user();
    }

    private function checkPermission(string $permission)
    {
        if ($this->branch instanceof Branch && !$this->branch->can($permission)) {
            throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
        } elseif ($this->employeeLogin instanceof EmployeeLogin && !$this->employeeLogin->can($permission)) {
            throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
        } elseif ($this->branchManager instanceof BranchManager && !$this->branchManager->can($permission)) {
            throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
        } elseif (!$this->branch && !$this->employeeLogin && !$this->branchManager) {
            abort(Response::HTTP_UNAUTHORIZED, 'يجب تسجيل الدخول');
        }
    }

    public function index($perPage = 10)
    {
        $this->checkPermission('Read-Inventories');
        $inventories = $this->inventoryService->index($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب المخزون بنجاح',
            'data' => $inventories
        ], Response::HTTP_OK);
    }

    public function getCategories()
    {
        $this->checkPermission('Create-Inventory');
        $branchId = $this->branch ? $this->branch->id : ($this->employeeLogin->employee->branch->id ?? null);
        $categories = $this->inventoryService->getCategories($branchId);
        return response()->json(['status' => true,  'message' => 'تم جلب الفئات بنجاح', 'data' => $categories], Response::HTTP_OK);
    }

    public function getSubCategoriesByCategory(Category $category)
    {
        if (!$category) {
            return response()->json(['status' => false, 'message' => 'الفئة غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $this->checkPermission('Create-Inventory');
        $subCategories = $this->inventoryService->getSubCategoriesByCategory($category);
        return response()->json(['status' => true,  'message' => 'تم جلب المخزون بنجاح', 'data' => $subCategories], Response::HTTP_OK);
    }

    public function getBranches()
    {
        $branch = $this->branch ? $this->branch : ($this->employeeLogin->employee->branch ?? null);
        if (!$branch) {
            return response()->json(['status' => false, 'message' => 'عفوا انت ليش مسجل دخول'], Response::HTTP_NOT_FOUND);
        }
        $this->checkPermission('Create-Inventory');
        $subCategories = $this->inventoryService->getBranches($branch);
        return response()->json(['status' => true,  'message' => 'تم جلب الافرع بنجاح', 'data' => $subCategories], Response::HTTP_OK);
    }

    /**
     * إنشاء مخزون جديد
     */
    public function store(Request $request)
    {
        $this->checkPermission('Create-Inventory');
        $branchId = $this->branch ? $this->branch->id : ($this->employeeLogin->employee->branch->id ?? null);
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', Rule::unique('inventories')->where(fn($query) => $query->where('branch_id', $branchId))],
            // 'code' => ['required', 'string', 'max:50', Rule::unique('inventories')->where(fn($query) => $query->where('branch_id', $branchId))],
            'category_id' => 'required|integer|exists:categories,id',
            'subCategories_id' => 'required|integer|exists:sub_categories,id',
            'price' => 'required|numeric|min:0',
            'type' => 'required|in:raw,product',
            'notes' => 'required|string|max:500',
            'quantity' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $data['branch_id'] = $branchId;
        $inventory = $this->inventoryService->createInventory($data);
        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء المخزون بنجاح',
            'data' => $this->inventoryService->formatInventory($inventory)
        ], Response::HTTP_CREATED);
    }

    /**
     * تعديل مخزون موجود
     */
    public function update(Request $request, Inventory $inventory)
    {
        $this->checkPermission('Update-Inventory');
        $branchId = $this->branch ? $this->branch->id : ($this->employeeLogin->employee->branch->id ?? null);
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', Rule::unique('inventories')->where(fn($query) => $query->where('branch_id', $branchId))->ignore($inventory->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('inventories')->where(fn($query) => $query->where('branch_id', $branchId))->ignore($inventory->id)],
            'category_id' => 'required|integer|exists:categories,id',
            'subCategories_id' => 'required|integer|exists:sub_categories,id',
            'price' => 'required|numeric|min:0',
            'type' => 'required|in:raw,product',
            'notes' => 'nullable|string|max:500',
            'quantity' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $inventory = $this->inventoryService->updateInventory($inventory->id, $data);
        return response()->json([
            'status' => true,
            'message' => 'تم تعديل بيانات المخزون بنجاح',
            'data' => $this->inventoryService->formatInventory($inventory)
        ], Response::HTTP_OK);
    }

    /**
     * حذف مخزون
     */
    public function destroy(Inventory $inventory)
    {
        $this->checkPermission('Delete-Inventory');
        $deleted = $this->inventoryService->deleteInventory($inventory->id);
        if (!$deleted) {
            return response()->json(['status' => false, 'message' => 'المخزون غير موجود'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف المخزون بنجاح'], Response::HTTP_OK);
    }

    public function indexBranchManager($perPage = 10)
    {
        // $this->checkPermission('Read-Inventories');
        $inventories = $this->inventoryService->indexBranchManager($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب المخزون بنجاح',
            'data' => $inventories
        ], Response::HTTP_OK);
    }
}
