<?php

namespace App\Exports;

use App\Exports\BaseExport;

class ClothExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Name',
            'Description',
            'Cloth Type ID',
            'Cloth Type Name',
            'Breast Size',
            'Waist Size',
            'Sleeve Size',
            'Status',
            'Notes',
            'Entity Type',
            'Entity ID',
            'Entity Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($cloth): array
    {
        // Get first inventory location
        $inventory = $cloth->inventories->first();
        $entityType = null;
        $entityId = null;
        $entityName = null;
        
        if ($inventory && $inventory->inventoriable) {
            $entity = $inventory->inventoriable;
            $entityType = strtolower(class_basename(get_class($entity)));
            $entityId = $entity->id;
            $entityName = $entity->name ?? '';
        }
        
        return [
            $cloth->id,
            $cloth->code,
            $cloth->name,
            $cloth->description,
            $cloth->cloth_type_id,
            $cloth->clothType?->name,
            $cloth->breast_size,
            $cloth->waist_size,
            $cloth->sleeve_size,
            $cloth->status,
            $cloth->notes,
            $entityType,
            $entityId,
            $entityName,
            $cloth->created_at?->format('Y-m-d H:i:s'),
            $cloth->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






