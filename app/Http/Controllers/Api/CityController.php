<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Country;
use App\Services\Cities\CityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class CityController extends Controller
{
    protected $cityService;
    protected ?Admin $admin;

    public function __construct(CityService $cityService)
    {
        $this->cityService = $cityService;
        $this->admin = auth('admin-api')->user();
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

    // Index
    public function index($perPage = 10)
    {
        $permissionCheck = $this->checkPermission('Read-Cities');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $cities = $this->cityService->getAllCities($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب المدن بنجاح',
            'data' => $cities
        ], Response::HTTP_OK);
    }

    public function getCityByCountry(Country $country)
    {
        //
        $permissionCheck = $this->checkPermission('Create-City');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $cities = $this->cityService->getAllCitiesByCountry($country);
        return response()->json(['status' => true, 'message' => 'تم جلب المدن بنجاح', 'data' => $cities], Response::HTTP_OK);
    }

    // Store
    public function store(Request $request)
    {
        $permissionCheck = $this->checkPermission('Create-City');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:45',
            'code' => 'required|string|max:50',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'country_id' => 'required|exists:countries,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $city = $this->cityService->createCity($validator->validated());
        $data = [
            'id' => $city->id ?? '',
            'name' => $city->name ?? '',
            'code' => $city->code ?? '',
            'country_name' => $city->country->name ?? '',
            'latitude' => $city->latitude ?? '',
            'longitude' => $city->longitude ?? '',
            'active' => $city->active ?? '',
            'created_at' => $city->created_at ? $city->created_at->format('d-m-Y') : '',
        ];
        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء المدينة بنجاح',
            'data' => $data
        ], Response::HTTP_CREATED);
    }

    // Update
    public function update(Request $request, string $id)
    {
        $permissionCheck = $this->checkPermission('Update-City');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:45',
            'code' => 'required|string|max:50',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'active' => 'required|boolean',
            'country_id' => 'required|exists:countries,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $city = $this->cityService->updateCity($id, $validator->validated());
        if (!$city) {
            return response()->json(['status' => false, 'message' => 'المدينة غير موجودة'], Response::HTTP_NOT_FOUND);
        }
        $data = [
            'id' => $city->id ?? '',
            'name' => $city->name ?? '',
            'code' => $city->code ?? '',
            'country_name' => $city->country->name ?? '',
            'latitude' => $city->latitude ?? '',
            'longitude' => $city->longitude ?? '',
            'active' => $city->active ?? '',
            'created_at' => $city->created_at ? $city->created_at->format('d-m-Y') : '',
        ];
        return response()->json([
            'status' => true,
            'message' => 'تم تعديل المدينة بنجاح',
            'data' => $data
        ], Response::HTTP_OK);
    }

    // Delete
    public function destroy(string $id)
    {
        $permissionCheck = $this->checkPermission('Delete-City');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $deleted = $this->cityService->deleteCity($id);
        if (!$deleted) {
            return response()->json(['status' => false,  'message' => 'المدينة غير موجودة'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف المدينة بنجاح'], Response::HTTP_OK);
    }
}
