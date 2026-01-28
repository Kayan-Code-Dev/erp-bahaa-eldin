<?php

namespace App\Services\Job;

use App\Models\BranchJob;
use App\Models\Department;

class JobService
{
    public function index($perPage = 10): array
    {
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $id = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $jobs = BranchJob::where('branch_id', '=', $id)->paginate($perPage);
        $mapped = $jobs->getCollection()->map(function ($job) {
            return [
                'id' => $job->id ?? '',
                'department' => $job->department->name ?? '',
                'name' => $job->name ?? '',
                'code' => $job->code ?? '',
                'description' => $job->description ?? '',
                'active' => $job->active,
                'department_id' => $job->department_id,
                'created_at' => $job->created_at ? $job->created_at->format('d-m-Y') : '',
            ];
        });
        return [
            'data' => $mapped,
            'current_page' => $jobs->currentPage(),
            'next_page_url' => $jobs->nextPageUrl(),
            'prev_page_url' => $jobs->previousPageUrl(),
            'total' => $jobs->total(),
        ];
    }


    public function getDepartment()
    {
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $id = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $departments = Department::where('branch_id', '=', $id)->where('active', '=', true)->get();
        return $departments;
    }

    public function createJob(array $data)
    {
        return BranchJob::create($data);
    }

    public function updateJob(string $id, array $data)
    {
        $job = BranchJob::find($id);
        if (!$job) {
            return null;
        }
        $job->update($data);
        return $job;
    }

    public function deleteJob(string $id)
    {
        $job = BranchJob::find($id);
        if (!$job) {
            return null;
        }
        $job->delete();
        return true;
    }
}
