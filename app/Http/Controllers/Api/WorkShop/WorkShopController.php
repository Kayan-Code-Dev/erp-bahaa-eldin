<?php

namespace App\Http\Controllers\Api\WorkShop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\WorkShops\WorkShopService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class WorkShopController extends Controller
{
    //
    protected WorkShopService $workShopService;

    public function __construct(WorkShopService $workShopService)
    {
        $this->workShopService = $workShopService;
    }
    /**
     * تحقق من الصلاحيات للمستخدم الحالي
     */
    private function checkPermission(string $permission)
    {
        $user = $this->workShopService->getCurrentUser();
        /** @var \Spatie\Permission\Traits\HasPermissions|Authenticatable $user */
        if (!$user || !method_exists($user, 'can') || !$user->can($permission)) {
            throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
        }
    }

    public function index($perPage = 10)
    {
        //
        $this->checkPermission('WorkShops-Management');
        $roles = $this->workShopService->index($perPage);
        return response()->json(['status' => true, 'message' => 'تم الإرسال بنجاح', 'data' => $roles]);
    }

    public function indexDetails($uuid)
    {
        //
        $this->checkPermission('WorkShops-Management');
        $order = Order::where('uuid', $uuid)->with(['client', 'rentOrder'])->first();
        if (!$order) {
            return response()->json(['status' => false,  'message' => 'الطلب غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $orderDetails = $this->workShopService->formatOrderDetails($order);
        return response()->json(['status' => true, 'message' => 'تم جلب تفاصيل الطلب بنجاح', 'data' => $orderDetails], Response::HTTP_OK);
    }

    public function acceptOrder($uuid)
    {
        $this->checkPermission('WorkShops-Management');
        $orders = $this->workShopService->acceptOrder($uuid);
        if ($orders == null) {
            return response()->json(['status' => false,   'message' => 'عذرا لقد تم اعتماد الطلب من قبل',], Response::HTTP_BAD_REQUEST);
        }
        return response()->json(['status' => true,   'message' => 'تم اعتماد الطلب بنجاح', 'data' => $orders], Response::HTTP_OK);
    }


    public function createInvoice(Request $request, $uuid)
    {
        $this->checkPermission('WorkShops-Management');
        $validator = Validator::make($request->all(), [
            'received_by'            => 'required|string|max:255',
            'received_at'            => 'nullable|date',
            'rental_start_date'      => 'nullable|date',
            'rental_end_date'        => 'nullable|date|after_or_equal:rental_start_date',
            'notes'                  => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $orders = $this->workShopService->showInvoice($uuid, $data);
        if (!$orders) {
            return response()->json(['status' => false,  'message' => 'الطلب غير موجود'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true,   'message' => 'تم جلب بيانات الفاتورة بنجاح', 'data' => $orders], Response::HTTP_OK);
    }
}
