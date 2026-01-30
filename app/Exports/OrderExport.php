<?php

namespace App\Exports;

use App\Exports\BaseExport;

class OrderExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Client Name',
            'Client National ID',
            'Client Phone',
            'Entity Type',
            'Entity Name',
            'Total Price',
            'Paid',
            'Remaining',
            'Status',
            'Visit Datetime',
            'Order Notes',
            'Discount Type',
            'Discount Value',
            'Items Count',
            'Items Summary',
            'Payments Count',
            'Custody Count',
            'Created At',
            'Updated At',
        ];
    }

    public function map($order): array
    {
        $clientName = ($order->client?->first_name ?? '') . ' ' . 
                     ($order->client?->middle_name ?? '') . ' ' . 
                     ($order->client?->last_name ?? '');
        $clientName = trim($clientName);
        
        $itemsSummary = $order->items->map(function ($item) {
            return $item->code . ' (' . $item->pivot->price . ')';
        })->join('; ');
        
        $entityName = $order->inventory?->inventoriable?->name ?? '';
        $entityType = class_basename($order->inventory?->inventoriable_type ?? '');
        if ($entityType) {
            $entityType = strtolower($entityType);
        }
        
        return [
            $order->id,
            $clientName,
            $order->client?->national_id,
            $order->client?->phones->first()?->phone,
            $entityType,
            $entityName,
            $order->total_price,
            $order->paid,
            $order->remaining,
            $order->status,
            $order->visit_datetime,
            $order->order_notes,
            $order->discount_type,
            $order->discount_value,
            $order->items->count(),
            $itemsSummary,
            $order->payments->count(),
            $order->custodies->count(),
            $order->created_at?->format('Y-m-d H:i:s'),
            $order->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






