<?php

namespace App\Exports;

use App\Exports\BaseExport;

class PhoneExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Client ID',
            'Client Name',
            'Client National ID',
            'Phone',
            'Type',
            'Created At',
            'Updated At',
        ];
    }

    public function map($phone): array
    {
        $clientName = '';
        $clientNationalId = '';
        if ($phone->client) {
            $client = $phone->client;
            $clientName = trim(($client->first_name ?? '') . ' ' . 
                             ($client->middle_name ?? '') . ' ' . 
                             ($client->last_name ?? ''));
            $clientNationalId = $client->national_id ?? '';
        }
        
        return [
            $phone->id,
            $phone->client_id,
            $clientName,
            $clientNationalId,
            $phone->phone,
            $phone->type,
            $phone->created_at?->format('Y-m-d H:i:s'),
            $phone->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






