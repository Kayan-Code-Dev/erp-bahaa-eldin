<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Country;
use App\Services\Countries\CountryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CountryController extends Controller
{
    protected $countryService;
    protected $currentUser;
    protected ?Admin $admin;

    public function __construct(CountryService $countryService)
    {
        $this->countryService = $countryService;
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

    // private function checkPermission(string $permission)
    // {
    //     $this->currentUser = null;

    //     foreach (array_keys(config('auth.guards')) as $guard) {
    //         if (auth($guard)->check()) {
    //             $this->currentUser = auth($guard)->user();
    //             break;
    //         }
    //     }

    //     if (! $this->currentUser) {
    //         return response()->json(['status' => false, 'message' => 'يجب أن تكون مسجلاً للدخول'], Response::HTTP_UNAUTHORIZED);
    //     }

    //     if (! method_exists($this->currentUser, 'can') || ! $this->currentUser->can($permission)) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'ليس لديك الصلاحية المطلوبة'
    //         ], Response::HTTP_FORBIDDEN)->send();
    //     }

    //     return $this->currentUser;
    // }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permissionCheck = $this->checkPermission('Read-Countries');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $data = $this->countryService->index();
        return response()->json(['status' => true, 'message' => 'تم جلب الدول بنجاح', 'data' => $data], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $permissionCheck = $this->checkPermission('Create-Country');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:countries,code',
            'currency_name' => 'required|string|max:50',
            'currency_symbol' => 'required|string|max:5',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        if ($request->hasFile('image')) {
            $imageName = time() . '_' . str_replace(' ', '', $request->name) . '.' . $request->file('image')->extension();
            $request->file('image')->storePubliclyAs('Countries', $imageName, ['disk' => 'public']);
            $data['image'] = 'Countries/' . $imageName;
        }
        $country = $this->countryService->createCountry($data);
        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء البلد بنجاح',
            'data' => [
                'id' => $country->id ?? '',
                'name' => $country->name ?? '',
                'code' => $country->code ?? '',
                'currency_symbol' => $country->currency_symbol ?? '',
                'image' => $country->image ? url('storage/' . $country->image) : null,
                'description' => $country->description ?? '',
                'active' => $country->active ?? '',
                'created_at' => $country->created_at ? $country->created_at->format('d-m-Y') : '',
            ],
        ], Response::HTTP_CREATED);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $permissionCheck = $this->checkPermission('Update-Country');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $country = Country::find($id);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => "required|string|max:10|unique:countries,code," . $country->id,
            'currency_name' => 'required|string|max:50',
            'currency_symbol' => 'required|string|max:5',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'active' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();
        if ($request->hasFile('image')) {
            $imageName = time() . '_' . str_replace(' ', '', $data['name']) . '.' . $request->file('image')->extension();
            $request->file('image')->storePubliclyAs('Countries', $imageName, ['disk' => 'public']);
            $data['image'] = 'Countries/' . $imageName;
        }

        $country = $this->countryService->updateCountry($country->id, $data);
        if (!$country) {
            return response()->json(['status' => false, 'message' => 'لا يوجد بيانات'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تعديل البلد بنجاح',
            'data' => [
                'id' => $country->id ?? '',
                'name' => $country->name ?? '',
                'code' => $country->code ?? '',
                'currency_symbol' => $country->currency_symbol ?? '',
                'image' => $country->image ? asset('storage/' . $country->image) : null,
                'description' => $country->description ?? '',
                'active' => $country->active ?? '',
                'created_at' => $country->created_at ? $country->created_at->format('d-m-Y') : '',
            ],
        ], Response::HTTP_OK);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $permissionCheck = $this->checkPermission('Delete-Country');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $deleted = $this->countryService->deleteCountry($id);
        if (!$deleted) {
            return response()->json(['status' => false, 'message' => 'الدولة غير موجودة'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف الدولة بنجاح'], Response::HTTP_OK);
    }
}
