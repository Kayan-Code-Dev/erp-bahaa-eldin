<?php

namespace App\Services\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

class CategoryService
{
    public function index($perPage = 10): array
    {
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $id = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $categories = Category::where('branch_id', '=', $id)->paginate($perPage);
        $mapped = $categories->getCollection()->map(function ($category) {
            return [
                'id' => $category->id ?? '',
                'branch_name' => $category->branch->name ?? '',
                'name' => $category->name ?? '',
                'description' => $category->description ?? '',
                'active' => $category->active ? true : false,
                'created_at' => $category->created_at ? $category->created_at->format('d-m-Y') : '',
            ];
        });
        return [
            'data' => $mapped,
            'current_page' => $categories->currentPage(),
            'next_page_url' => $categories->nextPageUrl(),
            'prev_page_url' => $categories->previousPageUrl(),
            'total' => $categories->total(),
        ];
    }


    public function createCategory(array $data)
    {
        return Category::create($data);
    }

    public function updateCategory(string $id, array $data)
    {
        $category = Category::find($id);
        if (!$category) {
            return null;
        }
        $category->update($data);
        return $category;
    }

    public function deleteCategory(string $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return null;
        }
        $category->delete();
        return true;
    }
}
