<?php

namespace App\Http\Controllers\Api\Categories;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\EmployeeLogin;
use App\Services\Categories\CategoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{

    protected CategoryService $categoryService;
    protected ?Branch $branch;
    protected ?EmployeeLogin $employeeLogin;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
        $this->branch = auth('branch-api')->user(); // فرع عنده صلاحيات
        $this->employeeLogin = auth('employee-api')->user(); // موظف عنده صلاحيات
    }

    private function checkPermission(string $permission)
    {
        if ($this->branch instanceof Branch) {
            if (!$this->branch->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } elseif ($this->employeeLogin instanceof EmployeeLogin) {
            if (!$this->employeeLogin->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } else {
            abort(Response::HTTP_UNAUTHORIZED, 'يجب تسجيل الدخول');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index($perPage = 10)
    {
        $permissionCheck = $this->checkPermission('Read-Categories');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $category = $this->categoryService->index($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الوظائف بنجاح',
            'data' => $category
        ], Response::HTTP_OK);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $permissionCheck = $this->checkPermission('Create-Category');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $id = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        // افترضنا أن $branchId هو الفرع الحالي
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->where(function ($query) use ($request) {
                return $query->where('branch_id', $request->branch_id);
            }),],
            'description' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $data['branch_id'] = $id;
        $category = $this->categoryService->createCategory($data);
        $data = [
            'id' => $category->id ?? '',
            'name' => $category->name ?? '',
            'description' => $category->description ?? '',
            'active' => true,
            'created_at' => $category->created_at ? $category->created_at->format('d-m-Y') : '',
        ];
        return response()->json(['status' => true, 'message' => 'تم إنشاء الفئة بنجاح', 'data' => $data], Response::HTTP_CREATED);
    }




    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        $permissionCheck = $this->checkPermission('Update-Category');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->where(function ($query) use ($request) {
                return $query->where('branch_id', $request->branch_id);
            })->ignore($category->id),],
            'description' =>  'required|string|max:255',
            'active' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $category = $this->categoryService->updateCategory($category->id, $data);
        if (!$category) {
            return response()->json(['status' => false, 'message' => 'الوظيفة غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $data = [
            'id' => $category->id ?? '',
            'name' => $category->name ?? '',
            'description' => $category->description ?? '',
            'active' => $category->active ?? '',
            'created_at' => $category->created_at ? $category->created_at->format('d-m-Y') : '',
        ];
        return response()->json([
            'status' => true,
            'message' => 'تم تعديل بيانات الفئة بنجاح',
            'data' => $data
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $permissionCheck = $this->checkPermission('Delete-Category');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $deleted = $this->categoryService->deleteCategory($category->id);
        if (!$deleted) {
            return response()->json(['status' => false,  'message' => 'الفئة غير موجودة'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف الفئة بنجاح'], Response::HTTP_OK);
    }
}
