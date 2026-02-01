<?php

namespace App\Exports;

class SupplierExport extends BaseExport
{
    /**
     * Get the headers for the export.
     */
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Code',
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
            $item->name,
            $item->code,
            $item->created_at?->format('Y-m-d H:i:s'),
            $item->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

