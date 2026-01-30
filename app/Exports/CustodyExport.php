<?php

namespace App\Exports;

use App\Exports\BaseExport;

class CustodyExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Order ID',
            'Client Name',
            'Client National ID',
            'Type',
            'Description',
            'Value',
            'Status',
            'Returned At',
            'Notes',
            'Photos Count',
            'Returns Count',
            'Created At',
            'Updated At',
        ];
    }

    public function map($custody): array
    {
        $clientName = '';
        $clientNationalId = '';
        if ($custody->order && $custody->order->client) {
            $client = $custody->order->client;
            $clientName = trim(($client->first_name ?? '') . ' ' . 
                             ($client->middle_name ?? '') . ' ' . 
                             ($client->last_name ?? ''));
            $clientNationalId = $client->national_id ?? '';
        }
        
        return [
            $custody->id,
            $custody->order_id,
            $clientName,
            $clientNationalId,
            $custody->type,
            $custody->description,
            $custody->value,
            $custody->status,
            $custody->returned_at?->format('Y-m-d H:i:s'),
            $custody->notes,
            $custody->photos->count(),
            $custody->returns->count(),
            $custody->created_at?->format('Y-m-d H:i:s'),
            $custody->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






