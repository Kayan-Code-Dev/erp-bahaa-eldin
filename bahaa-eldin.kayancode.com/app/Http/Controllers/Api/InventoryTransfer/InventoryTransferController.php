<?php

namespace App\Http\Controllers\Api\InventoryTransfer;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Models\Category;
use App\Models\EmployeeLogin;
use App\Models\Inventory;
use App\Models\InventoryTransfer;
use App\Services\InventoryTransfer\InventoryTransferService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InventoryTransferController extends Controller
{
    protected $service;
    protected ?BranchManager $branchManager;
    protected ?Branch $branch;
    protected ?EmployeeLogin $employeeLogin;

    public function __construct(InventoryTransferService $service)
    {
        $this->service = $service;
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
        $this->checkPermission('Read-InventoryTransfers');
        $transfers = $this->service->getTransfersForUser($this->branch, $this->branchManager, $this->employeeLogin, $perPage);
        return response()->json(['status' => true, 'message' => 'تم جلب عمليات النقل بنجاح', 'data' => $transfers], Response::HTTP_OK);
    }

    public function getBranches()
    {
        $this->checkPermission('Create-InventoryTransfer');
        $branchManager = auth('branchManager-api')->user();
        $branches = $this->service->getMyBranches($branchManager);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الفرع بنجاح',
            'data' => $branches
        ], Response::HTTP_OK);
    }

    public function getCategories(Branch $branch)
    {
        //
        $this->checkPermission('Create-InventoryTransfer');
        $branchId = $branch->id;
        $categories = $this->service->getCategories($branchId);
        return response()->json(['status' => true,  'message' => 'تم جلب الفئات بنجاح', 'data' => $categories], Response::HTTP_OK);
    }

    public function getSubCategoriesByCategory(Category $category)
    {
        $this->checkPermission('Create-InventoryTransfer');
        if (!$category) {
            return response()->json(['status' => false, 'message' => 'الفئة الفرعية غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $subCategories = $this->service->getSubCategoriesByCategory($category);
        return response()->json(['status' => true,  'message' => 'تم جلب الفئة الفرعية بنجاح', 'data' => $subCategories], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $this->checkPermission('Create-InventoryTransfer');
        try {
            $validated = $this->validateInventoryTransfer($request);
            if ($validated instanceof JsonResponse) {
                return $validated;
            }
            $transfer = $this->service->createTransfer($validated);
            $data = $this->service->formatInventoryTransfer($transfer);
            return response()->json(['status' => true, 'message' => 'تم إنشاء طلب النقل بنجاح',  'data' => $data,]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function approve($uuid)
    {
        $this->checkPermission('Approve-InventoryTransfer');
        $transfer = InventoryTransfer::where('uuid', $uuid)->first();
        if (!$transfer) {
            return response()->json(['status' => false, 'message' => 'لم يتم العثور على طلب النقل المطلوب.'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $this->service->approveTransfer($transfer, $this->currentUser());
            return response()->json(['status' => true, 'message' => 'تمت الموافقة',], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage(),], Response::HTTP_BAD_REQUEST);
        }
    }


    public function reject($uuid)
    {
        $this->checkPermission('Reject-InventoryTransfer');
        $transfer = InventoryTransfer::where('uuid', $uuid)->first();
        if (!$transfer) {
            return response()->json(['status' => false, 'message' => 'لم يتم العثور على طلب النقل المطلوب.'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $this->service->rejectTransfer($transfer, $this->currentUser());
            return response()->json(['status' => true,  'message' => 'تم الرفض',], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage(),], Response::HTTP_BAD_REQUEST);
        }
    }

    protected function validateInventoryTransfer(Request $request)
    {
        $fromBranchId = null;
        if ($this->branchManager) {
            $fromBranchId = $request->input('from_branch_id');
        } else {
            $fromBranchId = $this->branch?->id ?? $this->employeeLogin?->employee->branch_id;
            $request->merge(['from_branch_id' => $fromBranchId]);
        }
        $rules = [
            'from_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'to_branch_id'   => ['required', 'integer', 'different:from_branch_id', Rule::exists('branches', 'id')],
            'quantity'       => 'required|numeric|min:0.1',
            'category_id'    => 'required|integer|exists:categories,id',
            'subCategories_id' => 'required|integer|exists:sub_categories,id',
            'notes'          => 'nullable|string|max:500',
        ];
        if ($this->branchManager) {
            $rules['from_branch_id'][] = Rule::in($this->branchManager->manger->pluck('id')->toArray());
            $rules['inventory_id'] = 'nullable|integer|exists:inventories,id';
        } else {
            $rules['inventory_id'] = 'nullable|integer|exists:inventories,id';
        }
        $messages = [
            'from_branch_id.required' => 'يجب تحديد الفرع الذي سيتم النقل منه.',
            'from_branch_id.in'       => 'لا يمكنك تنفيذ النقل من فرع غير تابع لك.',
            'to_branch_id.different'  => 'لا يمكن النقل إلى نفس الفرع.',
            'quantity.min'            => 'الكمية يجب أن تكون أكبر من الصفر.',
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first(),], Response::HTTP_BAD_REQUEST);
        }
        $validated = $validator->validated();
        if (!isset($validated['inventory_id'])) {
            $inventory = Inventory::where('branch_id', $validated['from_branch_id'])->where('subCategories_id', $validated['subCategories_id'])->first();
            if (!$inventory) {
                return response()->json(['status' => false, 'message' => 'لا يوجد منتج في المخزن مطابق للفئة المحددة.',], Response::HTTP_BAD_REQUEST);
            }
            if ($inventory->quantity < $validated['quantity']) {
                return response()->json(['status' => false, 'message' => 'الكمية المطلوبة تفوق الكمية المتاحة في المخزن.',], Response::HTTP_BAD_REQUEST);
            }
            $validated['inventory_id'] = $inventory->id;
        }
        $validated['requested_by_id'] = $this->branch?->id ?? $this->employeeLogin?->employee->id ?? $this->branchManager?->id;
        $validated['requested_by_type'] = $this->branch ? Branch::class : ($this->employeeLogin ? EmployeeLogin::class : BranchManager::class);
        $validated['status'] = 'pending';
        $existingTransfer = InventoryTransfer::where('from_branch_id', $validated['from_branch_id'])->where('to_branch_id', $validated['to_branch_id'])->where('status', 'pending')->first();
        if ($existingTransfer) {
            return response()->json(['status' => false, 'message' => 'هناك طلب نقل مسبق بين هذين الفرعين لا يزال قيد الانتظار.',], Response::HTTP_BAD_REQUEST);
        }
        return $validated;
    }


    protected function currentUser()
    {
        return $this->branch ?? $this->employeeLogin ?? $this->branchManager;
    }
}
