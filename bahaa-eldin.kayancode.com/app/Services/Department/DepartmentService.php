<?php

namespace App\Services\Department;

use App\Models\Department;
use Illuminate\Support\Facades\Storage;

class DepartmentService
{
    public function index($perPage = 10): array
    {
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $id = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $departments = Department::where('branch_id', '=', $id)->paginate($perPage);
        $mapped = $departments->getCollection()->map(function ($department) {
            return [
                'id' => $department->id ?? '',
                'name' => $department->name ?? '',
                'code' => $department->code ?? '',
                'description' => $department->description ?? '',
                'active' => $department->active ? true : false ?? '',
                'created_at' => $department->created_at ? $department->created_at->format('d-m-Y') : '',
            ];
        });
        return [
            'data' => $mapped,
            'current_page' => $departments->currentPage(),
            'next_page_url' => $departments->nextPageUrl(),
            'prev_page_url' => $departments->previousPageUrl(),
            'total' => $departments->total(),
        ];
    }

    public function createDepartment(array $data)
    {
        return Department::create($data);
    }


    public function updateDepartment(string $id, array $data)
    {
        $department = Department::find($id);
        if (!$department) {
            return null;
        }
        $department->update($data);
        return $department;
    }

    public function deleteDepartment(string $id)
    {
        $department = Department::find($id);
        if (!$department) {
            return null;
        }
        $department->delete();
        return true;
    }
}
