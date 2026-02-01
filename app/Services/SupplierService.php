<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * SupplierService
 *
 * Service for managing suppliers.
 */
class SupplierService
{
    /**
     * Get paginated list of suppliers
     */
    public function list(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $query = Supplier::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a supplier by ID
     */
    public function find(int $id): Supplier
    {
        return Supplier::findOrFail($id);
    }

    /**
     * Create a new supplier
     */
    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    /**
     * Update an existing supplier
     */
    public function update(int $id, array $data): Supplier
    {
        $supplier = $this->find($id);
        $supplier->update($data);
        return $supplier;
    }

    /**
     * Delete a supplier
     */
    public function delete(int $id): bool
    {
        $supplier = $this->find($id);
        return $supplier->delete();
    }

    /**
     * Get all suppliers (for export)
     */
    public function all()
    {
        return Supplier::orderBy('created_at', 'desc')->get();
    }
}

