<?php

namespace App\Exports;

use App\Exports\BaseExport;

class AddressExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Street',
            'Building',
            'Notes',
            'City ID',
            'City Name',
            'Country ID',
            'Country Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($address): array
    {
        return [
            $address->id,
            $address->street,
            $address->building,
            $address->notes,
            $address->city_id,
            $address->city?->name,
            $address->city?->country_id,
            $address->city?->country?->name,
            $address->created_at?->format('Y-m-d H:i:s'),
            $address->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






