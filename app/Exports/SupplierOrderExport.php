<?php

namespace App\Exports;

class SupplierOrderExport extends BaseExport
{
    /**
     * Get the headers for the export.
     */
    public function headings(): array
    {
        return [
            'ID',
            'Order Number',
            'Supplier ID',
            'Supplier Name',
            'Category ID',
            'Category Name',
            'Subcategory ID',
            'Subcategory Name',
            'Branch ID',
            'Branch Name',
            'Order Date',
            'Status',
            'Total Amount',
            'Notes',
            'Created At',
            'Updated At',
        ];
    }

    /**
     * Map each item to a row.
     */
    public function map($item): array
    {
        return [
            $item->id,
            $item->order_number,
            $item->supplier_id,
            $item->supplier?->name,
            $item->category_id,
            $item->category?->name,
            $item->subcategory_id,
            $item->subcategory?->name,
            $item->branch_id,
            $item->branch?->name,
            $item->order_date?->format('Y-m-d'),
            $item->status,
            $item->total_amount,
            $item->notes,
            $item->created_at?->format('Y-m-d H:i:s'),
            $item->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

