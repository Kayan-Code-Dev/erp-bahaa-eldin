<?php

namespace App\Services\Factories;

use App\Helpers\OtpGenerator;
use App\Models\Branch;
use App\Models\EmployeeLogin;
use App\Models\Order;
use App\Models\ProductionOrder;
use App\Models\TailoringOrder;
use Carbon\Carbon;
use Carbon\Str;

class FactoryService
{
    public function index($perPage = 10)
    {
        $user = auth('employee-api')->user();
        $employee = $user->employee;
        $branch = $employee->branch;
        $branchManagerId = $branch->branch_manager_id;
        $branchIds = Branch::where('branch_manager_id', $branchManagerId)->pluck('id');
        $orders = Order::with(['client', 'tailoringOrder'])
            ->where('order_type', 'tailoring')
            ->where(function ($query) use ($branchIds) {
                $query->where(function ($q) use ($branchIds) {
                    $q->where('creator_type', Branch::class)
                        ->whereIn('creator_id', $branchIds);
                })->orWhere(function ($q) use ($branchIds) {
                    $q->where('creator_type', EmployeeLogin::class)
                        ->whereIn('creator_id', function ($subQuery) use ($branchIds) {
                            $subQuery->select('employee_logins.id')
                                ->from('employee_logins')
                                ->join('employees', 'employees.id', '=', 'employee_logins.employee_id')
                                ->whereIn('employees.branch_id', $branchIds);
                        });
                });
            })
            // ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->paginate($perPage);
        $formattedOrders = $orders->getCollection()->map(function ($order) {
            return $this->formatOrder($order);
        });
        $result = [
            'data'           => $formattedOrders->values(),
            'current_page'   => $orders->currentPage(),
            'next_page_url'  => $orders->nextPageUrl(),
            'prev_page_url'  => $orders->previousPageUrl(),
            'total'          => $orders->total(),
        ];
        return $result;
    }

    public function indexDetails($uuid)
    {
        $order = Order::with(['client', 'tailoringOrder'])->where('uuid', $uuid)->first();
        if (!$order) {
            return null;
        }
        return $this->formatOrder($order);
    }


    public function acceptOrder($uuid)
    {
        $order = Order::where('uuid', $uuid)->where('status', '=', 'pending')->first();
        if (!$order) {
            return null;
        }
        $order->update([
            'status' => 'processing',
        ]);
        ProductionOrder::create([
            'tailoring_order_id' => $order->tailoringOrder->id,
            'production_code' => 'PROD-' . OtpGenerator::generateNumeric(6),
            'status' => 'pending',
        ]);
        return $this->formatOrder($order);
    }

    public function startProduction($data, $uuid)
    {
        $order = Order::where('uuid', $uuid)->where('status', 'processing')->firstOrFail();
        $productionOrder = ProductionOrder::where('tailoring_order_id', $order->tailoringOrder->id)->firstOrFail();
        $productionOrder->update([
            'status' => 'in_progress',
            'start_date' => now(),
            'expected_finish_date' => $data['expected_finish_date'],
            'production_line' => $data['production_line'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        return $this->formatOrder($order);
    }

    public function updateStatusOrder($data, $uuid)
    {
        $order = Order::where('uuid', $uuid)->where('status', 'processing')->firstOrFail();
        $productionOrder = ProductionOrder::where('tailoring_order_id', $order->tailoringOrder->id)->firstOrFail();
        $productionOrder->update([
            'status' => $data['status'],
            'production_line' => $data['production_line'] ?? null,
            'produced_quantity' => $data['quantity'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $orderStatus = 'processing';
        if (in_array($data['status'], ['completed', 'canceled'])) {
            $orderStatus = $data['status'] === 'completed' ? 'done' : 'canceled';
        }
        $order->update([
            'status' => $orderStatus,
        ]);
        return $this->formatOrder($order);
    }





    private function formatOrder($order)
    {
        $branchName = '';
        if ($order->creator_type === \App\Models\Branch::class && $order->creator) {
            $branchName = $order->creator->name ?? '';
        }
        if ($order->creator_type === \App\Models\EmployeeLogin::class && $order->creator) {
            $branchName = $order->creator->employee->branch->name ?? '';
        }
        return [
            'uuid'          => $order->uuid ?? '',
            'order_number'  => $order->order_number ?? '',
            'branch_name'   => $branchName,
            'type_product'  => $order->tailoringOrder->model_name ?? '',
            'quantity'      => $order->tailoringOrder->quantity ?? '',
            'status'        => $order->tailoringOrder->productionOrder->status ?? 'waiting',
            'created_at'    => isset($order->created_at) ? Carbon::parse($order->created_at)->format('d-m-Y') : null,
            'delivery_date' => isset($order->tailoringOrder->delivery_date) ? Carbon::parse($order->tailoringOrder->delivery_date)->format('d-m-Y') : null,
            'notes'         => $order->tailoringOrder->notes ?? '',
        ];
    }
}
