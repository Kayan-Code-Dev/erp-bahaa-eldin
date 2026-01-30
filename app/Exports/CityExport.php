<?php

namespace App\Exports;

use App\Exports\BaseExport;

class CityExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Country ID',
            'Country Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($city): array
    {
        return [
            $city->id,
            $city->name,
            $city->country_id,
            $city->country?->name,
            $city->created_at?->format('Y-m-d H:i:s'),
            $city->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






