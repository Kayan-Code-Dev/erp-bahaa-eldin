<?php

namespace App\Exports;

use App\Exports\BaseExport;

class PaymentExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Order ID',
            'Client Name',
            'Client National ID',
            'Amount',
            'Status',
            'Payment Type',
            'Payment Date',
            'Notes',
            'Created By',
            'Created At',
            'Updated At',
        ];
    }

    public function map($payment): array
    {
        $clientName = '';
        $clientNationalId = '';
        if ($payment->order && $payment->order->client) {
            $client = $payment->order->client;
            $clientName = trim(($client->first_name ?? '') . ' ' . 
                             ($client->middle_name ?? '') . ' ' . 
                             ($client->last_name ?? ''));
            $clientNationalId = $client->national_id ?? '';
        }
        
        return [
            $payment->id,
            $payment->order_id,
            $clientName,
            $clientNationalId,
            $payment->amount,
            $payment->status,
            $payment->payment_type,
            $payment->payment_date?->format('Y-m-d H:i:s'),
            $payment->notes,
            $payment->user?->name,
            $payment->created_at?->format('Y-m-d H:i:s'),
            $payment->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






