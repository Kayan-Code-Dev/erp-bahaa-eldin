<?php

namespace App\Services\Orders;

use App\Models\Admin;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeLogin;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\RentOrder;
use App\Models\TailoringOrder;
use App\Models\WorkShop;
use App\Models\WorkshopInspection;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function indexOrders(string $orderType, int $perPage = 10): array
    {
        $user = $this->getCurrentUser();
        if (!$user) throw new \Exception('غير مصرح بالدخول');
        $creatorId   = $user->id;
        $creatorType = get_class($user);
        $orders = Order::with(['client', 'tailoringOrder', 'purchaseOrder', 'rentOrder'])
            ->where('order_type', $orderType)
            ->where('creator_id', $creatorId)
            ->where('creator_type', $creatorType)
            ->orderByDesc('created_at')
            ->paginate($perPage);
        $formattedOrders = $orders->getCollection()->map(function ($order) {
            $specificOrder = match ($order->order_type) {
                'tailoring' => $order->tailoringOrder,
                'purchase'  => $order->purchaseOrder,
                'rent'      => $order->rentOrder,
                default     => $order,
            };
            return $this->formatOrder($specificOrder ?? $order);
        });
        $result = [
            'data'         => $formattedOrders,
            'current_page' => $orders->currentPage(),
            'next_page_url' => $orders->nextPageUrl(),
            'prev_page_url' => $orders->previousPageUrl(),
            'total'        => $orders->total(),
        ];
        if (in_array($orderType, ['tailoring', 'rent'])) {
            $result['stats'] = $this->getOrderStats($orderType, $creatorId, $creatorType);
        }
        return $result;
    }

    public function getCategories($branchId)
    {
        $categories = Category::where('branch_id', '=', $branchId)->where('active', true)->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        });
        return $categories;
    }

    public function getSubCategoriesByCategory(Category $category)
    {
        $subCategories = $category->subCategories()->where('active', true)->get()->map(function ($subCategory) {
            return [
                'id' => $subCategory->id,
                'name' => $subCategory->name,
            ];
        });
        return $subCategories;
    }

    public function createOrder(string $orderType, array $data)
    {
        return DB::transaction(function () use ($orderType, $data) {

            $user = $this->getCurrentUser();
            if (!$user) {
                throw new \Exception('غير مصرح بالدخول');
            }

            $creatorId   = $user->id;
            $creatorType = get_class($user);

            // إنشاء العميل
            $client = Client::firstOrCreate(
                ['phone_primary' => $data['client_phone_primary']],
                [
                    'name' => $data['client_name'],
                    'phone_secondary' => $data['client_phone_secondary'] ?? null,
                    'address' => $data['client_address'],
                ]
            );

            // إنشاء الطلب العام
            $order = Order::create([
                'order_number' => $this->generateOrderNumber($orderType),
                'client_id' => $client->id,
                'creator_id' => $creatorId,
                'creator_type' => $creatorType,
                'order_type' => $orderType,
                'status' => $orderType === 'purchase' ? 'done' : 'pending',
                'delivery_date' => $data['delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // إنشاء الطلب التفصيلي حسب النوع
            $detailsMapping = [
                'tailoring' => TailoringOrder::class,
                'rent'      => RentOrder::class,
                'purchase'  => PurchaseOrder::class,
            ];

            $detailClass = $detailsMapping[$orderType] ?? null;
            if ($detailClass) {
                $detailData = match ($orderType) {
                    'tailoring' => [
                        'order_id' => $order->id,
                        'visit_date' => $data['visit_date'],
                        'event_date' => $data['event_date'],
                        'model_name' => $data['model_name'],
                        'fabric_preference' => $data['fabric_preference'] ?? null,
                        'measurements' => $data['measurements'] ?? null,
                        'quantity' => $data['quantity'] ?? null,
                        'delivery_date' => $data['delivery_date'] ?? null,
                        'source' => $data['source'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ],
                    'rent' => [
                        'order_id' => $order->id,
                        'sub_category_id' => $data['sub_category_id'] ?? null,
                        'model_name' => $data['model_name'],
                        'rental_duration' => $data['rental_duration'],
                        'measurements' => $data['measurements'] ?? null,
                        'event_date' => $data['event_date'],
                        'delivery_date' => $data['delivery_date'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ],
                    'purchase' => [
                        'order_id' => $order->id,
                        'sub_category_id' => $data['sub_category_id'] ?? null,
                        'model_name' => $data['model_name'],
                        'quantity' => $data['quantity'],
                        'customizations' => $data['customizations'] ?? null,
                        'delivery_date' => $data['delivery_date'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ],
                };

                $detail = $detailClass::create($detailData);
                if ($orderType === 'rent') {
                    $branchId = $creatorType === Branch::class ? $user->id : ($user->employee->branch->id ?? null);
                    $workshop = $branchId ? WorkShop::where('branch_id', $branchId)->first() : null;
                    if ($workshop) {
                        WorkshopInspection::create([
                            'order_id' => $order->id,
                        ]);
                    }
                }

                return $this->formatOrder($detail->relationLoaded('order') ? $detail : $detail->load('order.client'));
            }

            return $this->formatOrder($order);
        });
    }

    public function updateOrderStatus(Order $order, string $status): array
    {
        $order->status = $status;
        $order->delivery_date = Carbon::now();
        $order->save();
        return $this->formatOrder($order);
    }

    public function formatOrder($order): array
    {
        // تحديد نوع الطلب
        $type = match (true) {
            $order instanceof TailoringOrder => 'تفصيل',
            $order instanceof PurchaseOrder  => 'شراء',
            $order instanceof RentOrder      => 'تأجير',
            default => 'غير محدد',
        };
        // إذا الموديل الفرعي ما كان محمّل العلاقة order → نحملها
        if (method_exists($order, 'order') && ! $order->relationLoaded('order')) {
            $order->load('order.client');
        }
        // تحديد الموديل الأساسي
        $mainOrder = $order->order ?? $order;
        return [
            'uuid'          => $mainOrder->uuid ?? null,
            'order_number'  => $mainOrder->order_number ?? null,
            'client_name'   => $mainOrder->client?->name ?? 'غير معروف',
            'order_type'    => $mainOrder->order_type,
            'status'        => $mainOrder->status ?? 'غير معروف',
            'created_at'    => isset($mainOrder->created_at) ? Carbon::parse($mainOrder->created_at)->format('d-m-Y') : null,
            'delivery_date' => isset($mainOrder->delivery_date) ? Carbon::parse($mainOrder->delivery_date)->format('d-m-Y') : null,
        ];
    }

    public function formatOrderDetails($order): array
    {
        $order = $order ?? null;
        $client = $order?->client ?? null;
        $details = [];
        $type = null;

        if ($order?->order_type === 'tailoring') {
            $tailor = $order->tailoringOrder;
            $type = 'تفصيل';
            $details = [
                'visit_date'    => $tailor?->visit_date ? Carbon::parse($tailor->visit_date)->format('d-m-Y H:i') : null,
                'source'        => $tailor?->source ?? null,
                'order_type'    => $order->order_type,
                'order_number'  => $order->order_number ?? null,
                'model_name'    => $tailor?->model_name ?? null,
                'measurements'  => $tailor?->measurements,
                'quantity'  => $tailor?->quantity,
                'status'        => $order->status ?? 'غير معروف',
                'created_at'    => $order?->created_at ? Carbon::parse($order->created_at)->format('d-m-Y') : null,
                'delivery_date' => $tailor?->delivery_date ? Carbon::parse($tailor->delivery_date)->format('d-m-Y') : null,
                'notes'         => $tailor?->notes ?? $order?->notes ?? null,
            ];
        } elseif ($order?->order_type === 'rent') {
            $rent = $order->rentOrder;
            $type = 'تأجير';
            $details = [
                'event_date'      => $rent->created_at ? Carbon::parse($rent->created_at)->format('d-m-Y H:i') : null,
                'source'          => $rent->source ?? null,
                'order_type'      => $order->order_type,
                'order_number'    => $order->order_number ?? null,
                'model_name'      => $rent?->model_name ?? null,
                'category'        => $rent?->subCategory->category->name ?? null,
                'sub_category'    => $rent?->subCategory->name ?? null,
                'rental_duration' => $rent?->rental_duration ?? null,
                'status'          => $order->status ?? 'غير معروف',
                'created_at'      => $order?->created_at ? Carbon::parse($order->created_at)->format('d-m-Y') : null,
                'delivery_date'   => $rent?->delivery_date ? Carbon::parse($rent->delivery_date)->format('d-m-Y') : null,
                'notes'           => $rent?->notes ?? $order?->notes ?? null,
            ];
        } elseif ($order?->order_type === 'purchase') {
            $type = 'شراء';
            $details = [
                'message'      => 'هذا طلب شراء، لا توجد تفاصيل إضافية للعرض.',
                'order_type'   => $order->order_type,
                'quantity'      => $order?->purchaseOrder->quantity,
                'order_number' => $order->order_number ?? null,
                'status'       => $order->status ?? 'غير معروف',
            ];
        }

        // ✅ نرجع كل البيانات مرة واحدة
        return array_merge(
            [
                'uuid'           => $order?->uuid ?? null,
                'client_name'    => $client?->name ?? 'غير معروف',
                'phone_primary'  => $client?->phone_primary ?? null,
                'phone_secondary' => $client?->phone_secondary ?? null,
                'address'        => $client?->address ?? null,
            ],
            $details
        );
    }


    private function generateOrderNumber(string $orderType): string
    {
        $prefix = match ($orderType) {
            'tailoring' => 'T',
            'purchase'  => 'P',
            'rent'      => 'R',
            default     => 'O',
        };
        $lastOrder = Order::where('order_type', $orderType)->latest('id')->first();
        $nextNumber = $lastOrder ? ((int) preg_replace('/\D/', '', $lastOrder->order_number)) + 1 : 1;
        return sprintf('%s-%03d', $prefix, $nextNumber);
    }


    private function getOrderStats(string $orderType, int $creatorId, string $creatorType): array
    {
        $query = Order::where('order_type', $orderType)->where('creator_id', $creatorId)->where('creator_type', $creatorType);
        return [
            'total_orders'     => $query->count(),
            'pending_orders'   => (clone $query)->where('status', 'pending')->count(),
            'ready_orders'     => (clone $query)->where('status', 'processing')->count(),
            'delivered_orders' => (clone $query)->where('status', 'done')->count(),
        ];
    }


    public function getCurrentUser(): ?Authenticatable
    {
        return auth('branch-api')->user() ?? auth('employee-api')->user() ?? auth('admin-api')->user();
    }
}
