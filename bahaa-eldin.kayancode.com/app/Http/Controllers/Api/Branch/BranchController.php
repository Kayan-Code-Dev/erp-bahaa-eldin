<?php

namespace App\Http\Controllers\Api\Branch;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Services\Branches\BranchService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class BranchController extends Controller
{
    protected BranchService $branchService;
    protected ?Admin $admin;
    protected ?BranchManager $branchManager;


    public function __construct(BranchService $branchService)
    {
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

    // private function checkPermission(string $permission)
    // {
    //     if (! $this->admin instanceof Admin || ! $this->admin->can($permission)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'ليس لديك الصلاحية المطلوبة'
    //         ], Response::HTTP_FORBIDDEN)->send();
    //         exit;
    //     }
    // }

    public function index($perPage = 10)
    {
        $this->checkPermission('Read-Branches');
        $branches = $this->branchService->getAllBranches($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الفروع بنجاح',
            'data' => $branches
        ], Response::HTTP_OK);
    }

    public function getBranchManagers()
    {
        $branchManagers = $this->branchService->getAllBranchManagers();
        return response()->json([
            'status' => true,
            'message' => 'تم جلب مدراء الفروع بنجاح',
            'data' => $branchManagers
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $this->checkPermission('Create-Branch');
        $validator = Validator::make($request->all(), [
            // 'role_id'    => 'required|exists:roles,id',
            'branch_manager_id' => 'required|exists:branch_managers,id',
            'name' => 'required|string|max:100|unique:branches,name',
            'email' => 'required|email|unique:branches,email',
            'phone' => 'required|string|unique:branches,phone',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $branch = $this->branchService->createBranch($data);
        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الفرع بنجاح',
            'data' => $this->branchService->formatBranch($branch)

        ], Response::HTTP_CREATED);
    }


    public function showVerifyEmail(string $uuid)
    {
        $branch = Branch::where('uuid', $uuid)->first();
        if (! $branch) {
            return abort(Response::HTTP_BAD_REQUEST, ' الفرع غير موجود.');
        }
        return view('Branch.activate', ['branch' => $branch]);
    }



    public function verifyEmail(Request $request, string $uuid)
    {
        $branch = Branch::where('uuid', $uuid)->first();
        if (! $branch) {
            return response()->json(['status' => false,  'message' => 'الفرع غير موجود.'], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 400);
        }
        try {
            $this->branchService->verifyEmail(['otp' => $request->otp], $branch);
            return response()->view('Branch.activation_success');
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }



    public function update(Request $request, string $uuid)
    {
        $this->checkPermission('Update-Branch');
        $branch = Branch::where('uuid', $uuid)->first();
        if (!$branch) {
            return response()->json(['status' => false, 'message' => 'لم يتم العثور على الفرع'], Response::HTTP_BAD_REQUEST);
        }
        $validator = Validator::make($request->all(), [
            // 'role_id'    => 'required|exists:roles,id',
            'branch_manager_id' => 'required|exists:branch_managers,id',
            'name' => 'required|string|max:100|unique:branches,name,' . $branch->id,
            'email' => 'required|email|unique:branches,email,' . $branch->id,
            'phone' => 'required|string|unique:branches,phone,' . $branch->id,
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $branch = $this->branchService->updateBranch($data, $branch);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث بيانات الفرع بنجاح',
            'data' => $this->branchService->formatBranch($branch)
        ], Response::HTTP_OK);
    }

    public function destroy(string $uuid)
    {
        $this->checkPermission('Delete-Branch');
        $branch = Branch::where('uuid', $uuid)->first();

        if (!$branch) {
            return response()->json(['status' => false, 'message' => 'الفرع غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $this->branchService->deleteBranch($branch);
        return response()->json(['status' => true, 'message' => 'تم حذف الفرع بنجاح']);
    }



    public function getDeletedBranches()
    {
        $this->checkPermission('Read-DeletedBranches');
        $deletedBranches = $this->branchService->getDeletedBranches();
        return response()->json([
            'status' => true,
            'data'   => $deletedBranches
        ]);
    }

    public function restore(string $uuid)
    {
        $this->checkPermission('Restore-BranchDeleted');
        $branch = $this->branchService->restoreBranch($uuid);
        if (! $branch) {
            return response()->json(['status' => false, 'message' => 'مدير الفرع غير موجود أو لم يتم حذفه.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم استرجاع مدير الفرع بنجاح.', 'data' => $this->branchService->formatBranch($branch)], Response::HTTP_OK);
    }


    public function forceDelete(string $uuid)
    {
        $this->checkPermission('Force-BranchDeleted');
        $deleted = $this->branchService->forceDeleteBranch($uuid);
        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'مدير الفرع غير موجود.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف مدير الفرع نهائيًا بنجاح.'], Response::HTTP_OK);
    }


    public function block(string $uuid)
    {
        $this->checkPermission('Blocked-Branch');
        $branch = $this->branchService->toggleBlockBranch($uuid, true);
        if (! $branch) {
            return response()->json(['status' => false,  'message' => 'المشرف غير موجود.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حظر المشرف بنجاح.', 'data' => $this->branchService->formatBranch($branch)], Response::HTTP_OK);
    }
}
