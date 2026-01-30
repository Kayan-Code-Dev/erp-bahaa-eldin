<?php

namespace App\Http\Controllers\Api\BranchManagers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Models\Country;
use App\Services\Branches\BranchService;
use App\Services\BranchManagers\BranchManagerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class BranchManagerController extends Controller
{
    //
    protected $branchManagerService;
    protected $branchService;
    protected ?Admin $admin;
    protected ?BranchManager $branchManager;


    public function __construct(BranchManagerService $branchManager, BranchService $branchService)
    {
        $this->branchManagerService = $branchManager;
        $this->branchService = $branchService;
        $this->admin = auth('admin-api')->user();
        $this->branchManager = auth('branchManager-api')->user();
    }

    private function checkPermission(string $permission)
    {
        if ($this->admin instanceof Admin) {
            if (!$this->admin->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } elseif ($this->branchManager instanceof BranchManager) {
            if (!$this->branchManager->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } else {
            abort(Response::HTTP_UNAUTHORIZED, 'يجب تسجيل الدخول');
        }
    }


    public function index($perPage = 10)
    {
        $this->checkPermission('Read-BranchManagers');
        $branchManagers = $this->branchManagerService->getAllBranchManagers($perPage);
        return response()->json(['status' => true, 'message' => 'تم جلب مدراء الفروع بنجاح', 'data' => $branchManagers], Response::HTTP_OK);
    }

    public function getRoleBranchManager()
    {
        //
        $this->checkPermission('Create-BranchManager');
        $branchManagers = $this->branchManagerService->getRoleBranchManagerService();
        return response()->json(['status' => true, 'message' => 'تم جلب الصلاحيات الخاصة بمدراء الفروع بنجاح', 'data' => $branchManagers], Response::HTTP_OK);
    }

    public function getCountry()
    {
        $this->checkPermission('Create-BranchManager');
        $countries = $this->branchManagerService->getCountry();
        return response()->json(['status' => true, 'message' => 'تم جلب الدول بنجاح', 'data' => $countries], Response::HTTP_OK);
    }


    public function getCity(Country $country)
    {
        //
        $this->checkPermission('Create-BranchManager');
        $countries = $this->branchManagerService->getCities($country);
        return response()->json(['status' => true, 'message' => 'تم جلب الدول بنجاح', 'data' => $countries], Response::HTTP_OK);
    }


    public function store(Request $request)
    {
        $this->checkPermission('Create-BranchManager');
        $validator =  Validator::make($request->all(), [
            'role_id'    => 'required|exists:roles,id',
            'branch_name' => 'required|string|max:45|unique:branch_managers,branch_name',
            // 'branch_number' => 'required|string|max:45|unique:branch_managers,branch_number',
            'location' => 'nullable|string',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'first_name' => 'required|string|max:45',
            'last_name' => 'required|string|max:45',
            'phone' => 'required|string|unique:branch_managers,phone',
            'email' => 'required|email|unique:branch_managers,email',
            'id_number' => 'required|string|unique:branch_managers,id_number',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $branchManager = $this->branchManagerService->createBranchManager($data);
        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء مدير الفرع بنجاح',
            'data' => $this->branchManagerService->formatBranchManager($branchManager)
        ], Response::HTTP_CREATED);
    }


    public function showVerifyEmail(string $uuid)
    {
        $manager = BranchManager::where('uuid', $uuid)->first();
        if (! $manager) {
            return abort(Response::HTTP_BAD_REQUEST, 'مدير الفرع غير موجود.');
        }
        return view('BranchManager.activate', ['branchManager' => $manager]);
    }



    public function verifyEmail(Request $request, string $uuid)
    {
        $manager = BranchManager::where('uuid', $uuid)->first();
        if (! $manager) {
            return response()->json(['status' => false,  'message' => 'مدير الفرع غير موجود.'], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 400);
        }
        try {
            $this->branchManagerService->verifyEmail(['otp' => $request->otp], $manager);
            return response()->view('BranchManager.activation_success');
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $uuid)
    {
        // $this->checkPermission('Update-BranchManager');

        $manager = BranchManager::where('uuid', $uuid)->first();
        if (!$manager) {
            return response()->json(['status' => false, 'message' => 'لم يتم العثور على المشرف'], Response::HTTP_BAD_REQUEST);
        }
        $validator =  Validator::make($request->all(), [
            // 'role_id'    => 'required|exists:roles,id',
            'branch_name' => 'required|string|max:45|unique:branch_managers,branch_name,' . $manager->id,
            'branch_number' => 'required|string|max:45|unique:branch_managers,branch_number,' . $manager->id,
            'location' => 'nullable|string',
            'latitude'     => 'nullable|numeric|between:-90,90',
            'longitude'    => 'nullable|numeric|between:-180,180',
            'first_name' => 'required|string|max:45',
            'last_name' => 'required|string|max:45',
            'phone' => 'required|string|unique:branch_managers,phone,' . $manager->id,
            'email' => 'required|email|unique:branch_managers,email,' . $manager->id,
            'id_number' => 'required|string|unique:branch_managers,id_number,' . $manager->id,
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $manager = $this->branchManagerService->updateBranchManager($data, $manager);
        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث بيانات المشرف بنجاح',
            'data'    => $this->branchManagerService->formatBranchManager($manager)
        ], Response::HTTP_OK);
    }

    public function destroy(string $uuid)
    {
        $this->checkPermission('Delete-BranchManager');
        $manager = BranchManager::where('uuid', $uuid)->first();
        if (! $manager) {
            return response()->json(['status' => false, 'message' => 'مدير الفرع غير موجود.'], Response::HTTP_BAD_REQUEST);
        }
        $this->branchManagerService->deleteBranchManager($manager);
        return response()->json(['status' => true, 'message' => 'تم حذف  مدير الفرع بنجاح ']);
    }


    public function getDeletedBranchManagers()
    {
        $this->checkPermission('Read-DeletedBranchManagers');
        $deletedBranchManagers = $this->branchManagerService->getDeletedBranchManagers();

        return response()->json([
            'status' => true,
            'data'   => $deletedBranchManagers
        ]);
    }

    public function restore(string $uuid)
    {
        $this->checkPermission('Restore-BranchManagerDeleted');
        $branchManager = $this->branchManagerService->restoreBranchManager($uuid);
        if (! $branchManager) {
            return response()->json(['status' => false, 'message' => 'مدير الفرع غير موجود أو لم يتم حذفه.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم استرجاع مدير الفرع بنجاح.', 'data' => $this->branchManagerService->formatBranchManager($branchManager)], Response::HTTP_OK);
    }


    public function forceDelete(string $uuid)
    {
        $this->checkPermission('Force-BranchManagerDeleted');
        $deleted = $this->branchManagerService->forceDeleteBranchManager($uuid);
        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'مدير الفرع غير موجود.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف مدير الفرع نهائيًا بنجاح.'], Response::HTTP_OK);
    }


    public function block(string $uuid)
    {
        $this->checkPermission('Blocked-BranchManager');
        $branchManager = $this->branchManagerService->toggleBlockBranchManager($uuid, true);
        if (! $branchManager) {
            return response()->json(['status' => false,  'message' => 'المشرف غير موجود.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حظر المشرف بنجاح.', 'data' => $this->branchManagerService->formatBranchManager($branchManager)], Response::HTTP_OK);
    }
    //************************* CREATE BRANCHES *************************************** */

    public function getMyBranches($perPage = 10)
    {
        $this->checkPermission('Read-Branches'); // تحقق من صلاحية القراءة
        $branchManager = auth('branchManager-api')->user();
        $branches = $this->branchManagerService->getMyBranches($perPage, $branchManager);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الفروع الخاصة بك بنجاح',
            'data' => $branches,
        ], Response::HTTP_OK);
    }

    public function createBranches(Request $request)
    {
        $this->checkPermission('Create-Branch'); // تحقق من صلاحية الإنشاء
        $branchManager = auth('branchManager-api')->user();

        $validator = Validator::make($request->all(), [
            'name' => ['required',  'string', 'max:100', Rule::unique('branches')->where(function ($query) use ($branchManager) {
                return $query->where('branch_manager_id', $branchManager->id);
            }),],
            'email' => 'required|email|unique:branches,email',
            'phone' => 'required|string|unique:branches,phone',
            'location' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();
        $data['branch_manager_id'] = $branchManager->id;
        $branch = $this->branchService->createBranch($data);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الفرع بنجاح',
            'data' => $this->branchManagerService->formatBranch($branch)
        ], Response::HTTP_CREATED);
    }

    public function updateBranches(Request $request, string $uuid)
    {
        $this->checkPermission('Update-Branch'); // تحقق من صلاحية التحديث
        $branch = Branch::where('uuid', $uuid)->first();
        if (!$branch) {
            return response()->json(['status' => false, 'message' => 'لم يتم العثور على الفرع'], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:branches,name,' . $branch->id,
            'email' => 'required|email|unique:branches,email,' . $branch->id,
            'phone' => 'required|string|unique:branches,phone,' . $branch->id,
            'location' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();
        $branchManager = auth('branchManager-api')->user();
        $data['branch_manager_id'] = $branchManager->id;
        $branch = $this->branchService->updateBranch($data, $branch);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث بيانات الفرع بنجاح',
            'data' => $this->branchManagerService->formatBranch($branch)
        ], Response::HTTP_OK);
    }

    public function deleteBranches(string $uuid)
    {
        $this->checkPermission('Delete-Branch'); // تحقق من صلاحية الحذف
        $branch = Branch::where('uuid', $uuid)->first();
        if (!$branch) {
            return response()->json(['status' => false, 'message' => 'الفرع غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $this->branchService->deleteBranch($branch);
        return response()->json(['status' => true, 'message' => 'تم حذف الفرع بنجاح']);
    }

    public function blockMyBranch(string $uuid)
    {
        $this->checkPermission('Blocked-Branch'); // تحقق من صلاحية الحظر
        $branch = $this->branchService->toggleBlockBranch($uuid, true);
        if (! $branch) {
            return response()->json(['status' => false, 'message' => 'الفرع غير موجود.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حظر الفرع بنجاح.', 'data' => $this->branchService->formatBranch($branch)], Response::HTTP_OK);
    }

    public function getDeletedMyBranches()
    {
        $this->checkPermission('Read-DeletedBranches'); // صلاحية قراءة المحذوفات
        $deletedBranches = $this->branchService->getDeletedMyBranches();
        return response()->json([
            'status' => true,
            'data' => $deletedBranches
        ]);
    }

    public function restoreMyBranch(string $uuid)
    {
        $this->checkPermission('Restore-BranchDeleted'); // صلاحية الاسترجاع
        $branch = $this->branchService->restoreBranch($uuid);
        if (! $branch) {
            return response()->json(['status' => false, 'message' => ' الفرع غير موجود أو لم يتم حذفه.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم استرجاع الفرع بنجاح.', 'data' => $this->branchService->formatBranch($branch)], Response::HTTP_OK);
    }

    public function forceDeleteeMyBranch(string $uuid)
    {
        $this->checkPermission('Force-BranchDeleted'); // صلاحية الحذف النهائي
        $deleted = $this->branchService->forceDeleteBranch($uuid);
        if (! $deleted) {
            return response()->json(['status' => false, 'message' => ' الفرع غير موجود.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف  الفرع نهائيًا بنجاح.'], Response::HTTP_OK);
    }
}
