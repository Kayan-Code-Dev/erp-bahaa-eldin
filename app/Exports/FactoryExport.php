<?php

namespace App\Exports;

use App\Exports\BaseExport;

class FactoryExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Factory Code',
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

    public function map($factory): array
    {
        return [
            $factory->id,
            $factory->factory_code,
            $factory->name,
            $factory->address?->street,
            $factory->address?->building,
            $factory->address?->notes,
            $factory->address?->city?->name,
            $factory->address?->city?->country?->name,
            $factory->inventory?->id,
            $factory->inventory?->name,
            $factory->created_at?->format('Y-m-d H:i:s'),
            $factory->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






