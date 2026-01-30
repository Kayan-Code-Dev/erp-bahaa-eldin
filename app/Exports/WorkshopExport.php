<?php

namespace App\Exports;

use App\Exports\BaseExport;

class WorkshopExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Workshop Code',
            'Name',
            'Address Street',
            'Address Building',
            'Address Notes',
            'City Name',
            'Country Name',
            'Inventory ID',
            'Inventory Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($workshop): array
    {
        return [
            $workshop->id,
            $workshop->workshop_code,
            $workshop->name,
            $workshop->address?->street,
            $workshop->address?->building,
            $workshop->address?->notes,
            $workshop->address?->city?->name,
            $workshop->address?->city?->country?->name,
            $workshop->inventory?->id,
            $workshop->inventory?->name,
            $workshop->created_at?->format('Y-m-d H:i:s'),
            $workshop->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






