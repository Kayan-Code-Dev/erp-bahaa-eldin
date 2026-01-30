<?php

namespace App\Exports;

use App\Exports\BaseExport;

class UserExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Roles',
            'Created At',
            'Updated At',
        ];
    }

    public function map($user): array
    {
        $roles = $user->roles->pluck('name')->join(', ');
        
        return [
            $user->id,
            $user->name,
            $user->email,
            $roles,
            $user->created_at?->format('Y-m-d H:i:s'),
            $user->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}






