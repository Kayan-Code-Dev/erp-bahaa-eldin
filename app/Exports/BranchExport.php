<?php

namespace App\Exports;

use App\Exports\BaseExport;

class BranchExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Branch Code',
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

    public function map($branch): array
    {
        return [
            $branch->id,
            $branch->branch_code,
            $branch->name,
            $branch->address?->street,
            $branch->address?->building,
            $branch->address?->notes,
            $branch->address?->city?->name,
            $branch->address?->city?->country?->name,
            $branch->inventory?->id,
            $branch->inventory?->name,
            $branch->created_at?->format('Y-m-d H:i:s'),
            $branch->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






