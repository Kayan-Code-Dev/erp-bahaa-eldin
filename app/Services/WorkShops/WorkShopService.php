<?php

namespace App\Services\WorkShops;

use App\Models\EmployeeLogin;
use App\Models\WorkShop;
use App\Models\WorkshopInspection;
use App\Models\Order;
use App\Models\RentOrder;
use App\Models\WorkshopReceipt;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;

class WorkShopService
{

    public function index($perPage = 10)
    {
        $user = $this->getCurrentUser();
        if (! $user) {
            throw new Exception('غير مصرح بالدخول');
        }
        $creatorId   = $user->id;
        $creatorType = get_class($user);
        $branchId = $creatorType === EmployeeLogin::class ? $user->employee->branch->id : $user->id;
        $orders = Order::with(['rentOrder.order.client', 'workshopInspection'])
            ->where('order_type', 'rent')->where('creator_id', $creatorId)
            ->where('creator_type', $creatorType)->orderByDesc('created_at')->paginate($perPage);
        $formattedOrders = $orders->getCollection()->map(function ($order) {
            $rentOrder = $order->rentOrder;
            return $rentOrder ? $this->formatRentOrder($rentOrder) : null;
        })->filter();
        $result = [
            'data'           => $formattedOrders->values(),
            'current_page'   => $orders->currentPage(),
            'next_page_url'  => $orders->nextPageUrl(),
            'prev_page_url'  => $orders->previousPageUrl(),
            'total'          => $orders->total(),
        ];
        return $result;
    }


    public function formatOrderDetails($order): array
    {
        $user = $this->getCurrentUser();
        if (! $user) {
            throw new Exception('غير مصرح بالدخول');
        }
        $creatorId   = $user->id;
        $creatorType = get_class($user);
        $order = $order ?? null;
        $client = $order?->client ?? null;
        $details = [];
        $rent = $order->rentOrder;
        $employeeName = $creatorType === EmployeeLogin::class ? $user->employee->full_name : $user->name;
        $employeeType = $creatorType === EmployeeLogin::class ? 'موظف' : 'الفرع';
        $details = [
            'uuid'      => $order->uuid ?? null,
            'client_name'      => $client->name ?? null,
            'delivery_date'      => $rent->delivery_date ?? null,
            'phone_primary'      => $client->phone_primary ?? null,
            'event_date'        => $rent->event_date ?? null,
            'phone_secondary'      => $client->phone_secondary ?? null,
            'address'      => $client->address ?? null,
            'order_type'     => $order->order_type,
            'category_name'      => $rent->subCategory->category->name ?? null,
            'category_id'      => $rent->subCategory->category->id ?? null,
            'subCategory_name'      => $rent->subCategory->name ?? null,
            'subCategory_id'      => $rent->subCategory->id ?? null,
            'subCategory_id'      => $rent->subCategory->id ?? null,
            'notes'      => $rent->notes ?? null,
            'order_status'      => $order->status ?? null,
            'measurements'      => $rent->measurements ?? null,
            'employee_name'      => $employeeName ?? null,
            'employee_type'      => $employeeType ?? null,
        ];

        return $details;
    }

    public function acceptOrder($uuid)
    {
        $theOrder = Order::with('rentOrder', 'workshopInspection')->where('uuid', $uuid)->where('status', 'pending')->first();
        if (! $theOrder) {
            return null;
        }
        // تحديث حالة الطلب
        $theOrder->update([
            'status' => 'processing',
        ]);
        // تحديث حالة الورشة إذا موجودة
        if ($theOrder->workshopInspection) {
            $theOrder->workshopInspection->update([
                'status' => 'under_inspection',
                'inspection_employee_id' => $this->getCurrentUser()->id,
            ]);
        }
        return $theOrder;
    }


    public function showInvoice($uuid, $data)
    {
        $theOrder = Order::with('rentOrder', 'workshopInspection')->where('uuid', $uuid)->where('status', 'processing')->first();
        if (! $theOrder) {
            return null;
        }
        WorkshopReceipt::create([
            'workshop_inspection_id' => $theOrder->workshopInspection->id,
            'received_by' => $data['received_by'],
            'received_at' => $data['received_at'],
            'rental_start_date' => $data['rental_start_date'],
            'rental_end_date' => $data['rental_end_date'],
            'notes' => $data['notes'],
        ]);
        // $theOrder->workshopInspection->update([
        //     'delivery_employee_id' => '',
        // ]);
        $data = $this->formatOrderDetails($theOrder);
        return $data;
    }


    public function getCurrentUser(): ?Authenticatable
    {
        return auth('branch-api')->user() ?? auth('employee-api')->user() ?? auth('admin-api')->user();
    }

    public function formatRentOrder(RentOrder $rentOrder): array
    {
        if (! $rentOrder->relationLoaded('order')) {
            $rentOrder->load('order.client');
        }
        $order = $rentOrder->order;
        return [
            // من جدول orders
            'uuid'           => $order->uuid ?? null,
            'order_number'   => $order->order_number ?? null,
            'client_name'    => $order->client?->name ?? 'غير معروف',
            'status'         => $order->status ?? 'غير معروف',
            'order_type'     => $order->order_type,
            // من جدول rent_orders
            'model_name'      => $rentOrder->model_name ?? null,
            'rental_duration' => $rentOrder->rental_duration ?? null,
            'delivery_date'   => isset($rentOrder->delivery_date) ? Carbon::parse($rentOrder->delivery_date)->format('d-m-Y') : null,
            'source'          => $rentOrder->source ?? null,
            'notes'           => $rentOrder->notes ?? null,
            // من جدول orders (تاريخ الإنشاء)
            'created_at'      => isset($order->created_at) ? Carbon::parse($order->created_at)->format('d-m-Y') : null,
        ];
    }
}
