<?php

namespace App\Exports;

use App\Exports\BaseExport;

class ClothTypeExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Name',
            'Description',
            'Breast Size',
            'Waist Size',
            'Sleeve Size',
            'Notes',
            'Subcategories',
            'Created At',
            'Updated At',
        ];
    }

    public function map($clothType): array
    {
        $subcategories = $clothType->subcategories->pluck('name')->join(', ');
        
        return [
            $clothType->id,
            $clothType->code,
            $clothType->name,
            $clothType->description,
            $clothType->breast_size,
            $clothType->waist_size,
            $clothType->sleeve_size,
            $clothType->notes,
            $subcategories,
            $clothType->created_at?->format('Y-m-d H:i:s'),
            $clothType->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






