<?php

namespace App\Http\Controllers\Api\Orders;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Services\Orders\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * تحقق من الصلاحيات للمستخدم الحالي
     */
    private function checkPermission(string $permission)
    {
        $user = $this->orderService->getCurrentUser();
        /** @var \Spatie\Permission\Traits\HasPermissions|Authenticatable $user */
        if (!$user || !method_exists($user, 'can') || !$user->can($permission)) {
            throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
        }
    }

    /**
     * جلب الطلبات حسب النوع
     */
    public function indexOrders(Request $request, string $orderType, int $perPage = 10)
    {
        $this->checkPermission('Read-Orders');
        $orders = $this->orderService->indexOrders($orderType, $perPage);
        return response()->json(['status' => true,  'message' => 'تم جلب الطلبات بنجاح',  'data' => $orders], Response::HTTP_OK);
    }

    /**
     * جلب الفئات للفرع
     */
    public function getCategories()
    {
        $this->checkPermission('Create-Order');
        $user = $this->orderService->getCurrentUser();
        $branchId = $user->branch->id ?? $user->id ?? null; // حسب نوع المستخدم
        $categories = $this->orderService->getCategories($branchId);
        return response()->json(['status' => true,  'message' => 'تم جلب الفئات بنجاح', 'data' => $categories], Response::HTTP_OK);
    }

    /**
     * جلب الفئات الفرعية حسب الفئة
     */
    public function getSubCategoriesByCategory(Category $category)
    {
        if (!$category) {
            return response()->json(['status' => false, 'message' => 'الفئة غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $this->checkPermission('Create-Order');
        $subCategories = $this->orderService->getSubCategoriesByCategory($category);
        return response()->json(['status' => true,  'message' => 'تم جلب الفئات الفرعية بنجاح', 'data' => $subCategories], Response::HTTP_OK);
    }

    /**
     * إنشاء طلب حسب النوع
     */
    public function storeOrder(Request $request, string $orderType)
    {
        $this->checkPermission('Create-Order');
        $rules = $this->getValidationRules($orderType);
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $order = $this->orderService->createOrder($orderType, $data);
        return response()->json(['status' => true, 'message' => 'تم إنشاء الطلب بنجاح.', 'data' => $order], Response::HTTP_OK);
    }

    /**
     * قواعد التحقق من صحة البيانات لكل نوع طلب
     */
    protected function getValidationRules(string $orderType): array
    {
        $commonClientRules = [
            'client_name' => 'required|string|max:100',
            'client_phone_primary' => 'required|string|max:20',
            'client_phone_secondary' => 'nullable|string|max:20',
            'client_address' => 'required|string|max:255',
            'visit_date' => 'required|date|after_or_equal:today',
            'event_date' => 'required|date|after_or_equal:visit_date',
            'source' => 'nullable|string|max:100',
        ];
        return match ($orderType) {
            'tailoring' => array_merge($commonClientRules, [
                'order_type' => 'required|in:tailoring',
                'model_name' => 'required|string|max:100',
                'fabric_preference' => 'required|string|max:100',
                'measurements' => 'required|array',
                'quantity' => 'required|integer|min:1',
                'delivery_date' => 'required|date|after_or_equal:today',
                'notes' => 'nullable|string',
            ]),

            'rent' => array_merge($commonClientRules, [
                'category_id' => 'required|integer|exists:categories,id',
                'sub_category_id' => 'required|integer|exists:sub_categories,id',
                'model_name' => 'required|string|max:100',
                'rental_duration' => 'required|integer|min:1',
                'measurements' => 'required|array',
                'event_date' => 'required|date|after_or_equal:visit_date',
                'delivery_date' => 'required|date|after_or_equal:today',
                'notes' => 'nullable|string',
            ]),

            'purchase' => array_merge($commonClientRules, [
                'category_id' => 'required|integer|exists:categories,id',
                'sub_category_id' => 'required|integer|exists:sub_categories,id',
                'model_name' => 'required|string|max:100',
                'quantity' => 'required|integer|min:1',
                'customizations' => 'required|array',
                'delivery_date' => 'required|date|after_or_equal:today',
                'notes' => 'nullable|string',
            ]),
            default => []
        };
    }

    /**
     * عرض تفاصيل الطلب
     */
    public function show($uuid)
    {
        $this->checkPermission('Read-Orders');
        $order = Order::where('uuid', $uuid)->with(['client', 'tailoringOrder', 'rentOrder', 'purchaseOrder'])->first();
        if (!$order) {
            return response()->json(['status' => false,  'message' => 'الطلب غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $orderDetails = $this->orderService->formatOrderDetails($order);
        return response()->json(['status' => true, 'message' => 'تم جلب تفاصيل الطلب بنجاح', 'data' => $orderDetails], Response::HTTP_OK);
    }

    /**
     * تحديث حالة الطلب
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $this->checkPermission('Read-Orders');
        $order = Order::where('uuid', $uuid)->first();
        if (!$order) {
            return response()->json(['status' => false, 'message' => 'الطلب غير موجود'], 404);
        }
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,done,canceled',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $updatedOrder = $this->orderService->updateOrderStatus($order, $request->status);
        return response()->json(['status' => true, 'message' => 'تم تحديث حالة الطلب بنجاح', 'data' => $updatedOrder], Response::HTTP_OK);
    }
}
