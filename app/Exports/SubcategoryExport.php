<?php

namespace App\Exports;

use App\Exports\BaseExport;

class SubcategoryExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Category ID',
            'Category Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($subcategory): array
    {
        return [
            $subcategory->id,
            $subcategory->name,
            $subcategory->category_id,
            $subcategory->category?->name,
            $subcategory->created_at?->format('Y-m-d H:i:s'),
            $subcategory->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






