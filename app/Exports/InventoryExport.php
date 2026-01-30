<?php

namespace App\Exports;

use App\Exports\BaseExport;

class InventoryExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Inventoriable Type',
            'Inventoriable ID',
            'Entity Name',
            'Entity Code',
            'Clothes Count',
            'Orders Count',
            'Created At',
            'Updated At',
        ];
    }

    public function map($inventory): array
    {
        $entityName = '';
        $entityCode = '';
        if ($inventory->inventoriable) {
            $entity = $inventory->inventoriable;
            $entityName = $entity->name ?? '';
            $entityCode = $entity->branch_code ?? $entity->workshop_code ?? $entity->factory_code ?? '';
        }
        
        return [
            $inventory->id,
            $inventory->name,
            class_basename($inventory->inventoriable_type ?? ''),
            $inventory->inventoriable_id,
            $entityName,
            $entityCode,
            $inventory->clothes->count(),
            $inventory->orders->count(),
            $inventory->created_at?->format('Y-m-d H:i:s'),
            $inventory->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






