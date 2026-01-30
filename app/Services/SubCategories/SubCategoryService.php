<?php

namespace App\Services\SubCategories;

use App\Models\Category;
use App\Models\SubCategory;

class SubCategoryService
{
    public function index($perPage = 10): array
    {
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $id = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $subCategories = SubCategory::whereHas('category', function ($query) use ($id) {
            $query->where('branch_id', $id);
        })->paginate($perPage);
        $mapped = $subCategories->getCollection()->map(fn($subCategory) => $this->formatSubCategory($subCategory));
        return [
            'data' => $mapped,
            'current_page' => $subCategories->currentPage(),
            'next_page_url' => $subCategories->nextPageUrl(),
            'prev_page_url' => $subCategories->previousPageUrl(),
            'total' => $subCategories->total(),
        ];
    }

    public function indexMyCategories()
    {
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $id = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $categories = Category::where('branch_id', '=', $id)->where('active', '=', true)->get()->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        });
        return $categories;
    }


    public function createSubCategory(array $data)
    {
        return SubCategory::create($data);
    }

    public function updateSubCategory(string $id, array $data)
    {
        $subCategory = SubCategory::find($id);
        if (!$subCategory) {
            return null;
        }
        $subCategory->update($data);
        return $subCategory;
    }

    public function deleteSubCategory(string $id)
    {
        $subCategory = SubCategory::find($id);
        if (!$subCategory) {
            return null;
        }
        $subCategory->delete();
        return true;
    }


    public function formatSubCategory(SubCategory $subCategory): array
    {
        $data = [
            'id' => $subCategory->id ?? '',
            'branch_name' => $subCategory->category->branch->name ?? '',
            'category_id' => $subCategory->category_id ?? '',
            'category_name' => $subCategory->category->name ?? '',
            'name' => $subCategory->name ?? '',
            'description' => $subCategory->description ?? '',
            'active' => $subCategory->active ? true : false,
            'created_at' => $subCategory->created_at ? $subCategory->created_at->format('d-m-Y') : '',
        ];
        return $data;
    }
}
