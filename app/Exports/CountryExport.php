<?php

namespace App\Exports;

use App\Exports\BaseExport;

class CountryExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($country): array
    {
        return [
            $country->id,
            $country->name,
            $country->created_at?->format('Y-m-d H:i:s'),
            $country->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






