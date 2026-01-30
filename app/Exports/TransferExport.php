<?php

namespace App\Exports;

use App\Exports\BaseExport;

class TransferExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'From Entity Type',
            'From Entity ID',
            'From Entity Name',
            'To Entity Type',
            'To Entity ID',
            'To Entity Name',
            'Transfer Date',
            'Status',
            'Notes',
            'Items Count',
            'Items Summary',
            'Created At',
            'Updated At',
        ];
    }

    public function map($transfer): array
    {
        $itemsSummary = $transfer->items->map(function ($item) {
            return $item->cloth_code . ' (' . $item->status . ')';
        })->join('; ');
        
        return [
            $transfer->id,
            $transfer->from_entity_type,
            $transfer->from_entity_id,
            $transfer->fromEntity?->name,
            $transfer->to_entity_type,
            $transfer->to_entity_id,
            $transfer->toEntity?->name,
            $transfer->transfer_date?->format('Y-m-d'),
            $transfer->status,
            $transfer->notes,
            $transfer->items->count(),
            $itemsSummary,
            $transfer->created_at?->format('Y-m-d H:i:s'),
            $transfer->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






