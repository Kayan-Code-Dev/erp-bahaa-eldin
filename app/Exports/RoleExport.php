<?php

namespace App\Exports;

use App\Exports\BaseExport;

class RoleExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Users Count',
            'Users',
            'Created At',
            'Updated At',
        ];
    }

    public function map($role): array
    {
        $users = $role->users->pluck('name')->join(', ');
        
        return [
            $role->id,
            $role->name,
            $role->users->count(),
            $users,
            $role->created_at?->format('Y-m-d H:i:s'),
            $role->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






