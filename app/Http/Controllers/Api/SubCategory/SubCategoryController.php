<?php

namespace App\Http\Controllers\Api\SubCategory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeeLogin;
use App\Models\SubCategory;
use App\Services\SubCategories\SubCategoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SubCategoryController extends Controller
{
    protected SubCategoryService $subCategoryService;
    protected ?Branch $branch;
    protected ?EmployeeLogin $employeeLogin;

    public function __construct(SubCategoryService $subCategoryService)
    {
        $this->subCategoryService = $subCategoryService;
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
        $permissionCheck = $this->checkPermission('Read-SubCategories');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $subCategory = $this->subCategoryService->index($perPage);
        return response()->json(['status' => true, 'message' => 'تم جلب الفئات الفرعية بنجاح', 'data' => $subCategory], Response::HTTP_OK);
    }

    public function indexMyCategories()
    {
        //
        $permissionCheck = $this->checkPermission('Create-SubCategory');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $getCategories = $this->subCategoryService->indexMyCategories();
        return response()->json(['status' => true, 'message' => 'تم جلب الفئات  بنجاح', 'data' => $getCategories], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $permissionCheck = $this->checkPermission('Create-SubCategory');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:categories,id',
            'name' => ['required', 'string', 'max:100', Rule::unique('sub_categories')->where(function ($query) use ($request) {
                return $query->where('category_id', $request->category_id);
            }),],
            'description' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $subCategory = $this->subCategoryService->createSubCategory($data);
        $data = $this->subCategoryService->formatSubCategory($subCategory);
        return response()->json(['status' => true, 'message' => 'تم إنشاء الفئة الفرعية بنجاح', 'data' => $data], Response::HTTP_CREATED);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SubCategory $subCategory)
    {
        $permissionCheck = $this->checkPermission('Update-SubCategory');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:categories,id',
            'name' => ['required', 'string', 'max:100', Rule::unique('sub_categories')->where(function ($query) use ($request) {
                return $query->where('category_id', $request->category_id);
            })->ignore($subCategory->id),],
            'description' =>  'required|string|max:255',
            'active' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $subCategory = $this->subCategoryService->updateSubCategory($subCategory->id, $data);
        if (!$subCategory) {
            return response()->json(['status' => false, 'message' => 'الفئة الفرعية غير موجودة'], Response::HTTP_NOT_FOUND);
        }
        $data = $this->subCategoryService->formatSubCategory($subCategory);
        return response()->json(['status' => true, 'message' => 'تم تعديل بيانات الفئة الفرعية بنجاح', 'data' => $data], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubCategory $subCategory)
    {
        $permissionCheck = $this->checkPermission('Delete-SubCategory');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $deleted = $this->subCategoryService->deleteSubCategory($subCategory->id);
        if (!$deleted) {
            return response()->json(['status' => false,  'message' => 'الفئة الفرعية غير موجودة'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف الفئة الفرعية بنجاح'], Response::HTTP_OK);
    }
}
