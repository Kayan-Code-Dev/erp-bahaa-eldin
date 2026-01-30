<?php

namespace App\Http\Controllers\Api;

use App\Helpers\OtpGenerator;
use App\Http\Controllers\Controller;
use App\Mail\AdminPasswordMail;
use App\Mail\WelcomeAdminMail;
use App\Models\Admin;
use App\Models\City;
use App\Models\Country;
use App\Services\Admins\AdminService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    #mohammad
    protected $adminService;
    protected ?Admin $admin;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
        $this->admin = auth('admin-api')->user(); // خزنه مرة واحدة

    }

    private function checkPermission(string $permission)
    {
        if ($this->admin instanceof Admin) {
            if (!$this->admin->can($permission)) {
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
        $this->checkPermission('Read-Admins');
        $admins = $this->adminService->getAllAdmins($perPage);
        return response()->json(['status' => true, 'message' => 'تم جلب الأدمن بنجاح', 'data' => $admins], Response::HTTP_OK);
    }

    public function getRoleAdmin()
    {
        //
        $this->checkPermission('Create-Admin');
        $roles = $this->adminService->getRoleAdmin();
        return response()->json(['status' => true, 'message' => 'تم جلب الادوار الخاصة بنجاح', 'data' => $roles], Response::HTTP_OK);
    }


    public function getCountry()
    {
        $this->checkPermission('Create-Admin');
        $countries = $this->adminService->getCountry();
        return response()->json(['status' => true, 'message' => 'تم جلب الدول بنجاح', 'data' => $countries], Response::HTTP_OK);
    }


    public function getCity(Country $country)
    {
        //
        $this->checkPermission('Create-Admin');
        $countries = $this->adminService->getCities($country);
        return response()->json(['status' => true, 'message' => 'تم جلب الدول بنجاح', 'data' => $countries], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $this->checkPermission('Create-Admin');
        $validator =  Validator::make($request->all(), [
            'role_id'    => 'required|exists:roles,id', // هذا السطر أضفناه
            'first_name' => 'required|string|max:45',
            'last_name' => 'required|string|max:45',
            'email' => 'required|email|unique:admins,email',
            'phone' => 'required|string|unique:admins,phone',
            'id_number' => 'required|string|unique:admins,id_number',
            'country_id' => 'required|exists:countries,id',
            'city_id' => 'required|exists:cities,id',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $admin = $this->adminService->createAdmin($data);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الأدمن بنجاح',
            'data' => $this->adminService->formatAdmin($admin)
        ], Response::HTTP_CREATED);
    }


    public function showVerifyEmail(string $uuid)
    {
        $admin = Admin::where('uuid', $uuid)->first();

        if (! $admin) {
            return abort(Response::HTTP_BAD_REQUEST, 'المشرف غير موجود.');
        }
        return view('Admins.activate', ['admin' => $admin]);
    }



    public function verifyEmail(Request $request, string $uuid)
    {
        $admin = Admin::where('uuid', $uuid)->first();
        if (! $admin) {
            return response()->json(['status' => false,  'message' => 'المشرف غير موجود.'], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 400);
        }
        try {
            $this->adminService->verifyEmail(['otp' => $request->otp], $admin);
            return view('Admins.activate', ['admin' => $admin]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }




    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $uuid)
    {
        $this->checkPermission('Update-Admin');

        $admin = Admin::where('uuid', $uuid)->first();
        if (!$admin) {
            return response()->json(['status' => false, 'message' => 'لم يتم العثور على المشرف'], Response::HTTP_BAD_REQUEST);
        }
        $validator = Validator::make($request->all(), [
            'role_id'    => 'required|exists:roles,id', // هذا السطر أضفناه
            'first_name' => 'required|string|max:45',
            'last_name'  => 'required|string|max:45',
            'email'      => 'required|email|unique:admins,email,' . $admin->id,
            'phone'      => 'required|string|unique:admins,phone,' . $admin->id,
            'id_number'  => 'required|string|unique:admins,id_number,' . $admin->id,
            'country_id' => 'required|exists:countries,id',
            'city_id'    => 'required|exists:cities,id',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $admin = $this->adminService->updateAdmin($data, $admin);
        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث بيانات المشرف بنجاح',
            'data'    => $this->adminService->formatAdmin($admin)
        ], Response::HTTP_OK);
    }



    public function destroy(string $uuid)
    {
        $this->checkPermission('Delete-Admin');
        $admin = Admin::where('uuid', $uuid)->first();
        if (! $admin) {
            return response()->json(['status' => false, 'message' => 'المشرف غير موجود.'], Response::HTTP_BAD_REQUEST);
        }
        $this->adminService->deleteAdmin($admin);
        return response()->json(['status' => true, 'message' => 'تم حذف المشرف بنجاح ']);
    }


    public function getDeletedAdmins()
    {
        $this->checkPermission('Read-DeletedAdmins');
        $deletedAdmins = $this->adminService->getDeletedAdmins();

        return response()->json([
            'status' => true,
            'data'   => $deletedAdmins
        ]);
    }

    public function restore(string $uuid)
    {
        $this->checkPermission('Restore-AdminDeleted');
        $admin = $this->adminService->restoreAdmin($uuid);
        if (! $admin) {
            return response()->json(['status' => false, 'message' => 'المشرف غير موجود أو لم يتم حذفه.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم استرجاع المشرف بنجاح.', 'data' => $this->adminService->formatAdmin($admin)], Response::HTTP_OK);
    }


    public function forceDelete(string $uuid)
    {
        $this->checkPermission('Force-AdminDeleted');
        $deleted = $this->adminService->forceDeleteAdmin($uuid);
        if (! $deleted) {
            return response()->json(['status' => false, 'message' => 'المشرف غير موجود.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف المشرف نهائيًا بنجاح.'], Response::HTTP_OK);
    }


    public function block(string $uuid)
    {
        $this->checkPermission('Blocked-Admin');
        $admin = $this->adminService->toggleBlockAdmin($uuid, true);
        if (! $admin) {
            return response()->json(['status' => false,  'message' => 'المشرف غير موجود.'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حظر المشرف بنجاح.', 'data' => $this->adminService->formatAdmin($admin)], Response::HTTP_OK);
    }
}
