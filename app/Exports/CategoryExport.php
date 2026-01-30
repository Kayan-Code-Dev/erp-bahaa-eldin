<?php

namespace App\Exports;

use App\Exports\BaseExport;

class CategoryExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Subcategories',
            'Created At',
            'Updated At',
        ];
    }

    public function map($category): array
    {
        $subcategories = $category->subcategories->pluck('name')->join(', ');
        
        return [
            $category->id,
            $category->name,
            $subcategories,
            $category->created_at?->format('Y-m-d H:i:s'),
            $category->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






