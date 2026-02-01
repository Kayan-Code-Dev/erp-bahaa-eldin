<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name,
                    'code' => $this->supplier->code,
                ];
            }),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'subcategory_id' => $this->subcategory_id,
            'subcategory' => $this->whenLoaded('subcategory', function () {
                return [
                    'id' => $this->subcategory->id,
                    'name' => $this->subcategory->name,
                ];
            }),
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', function () {
                return [
                    'id' => $this->branch->id,
                    'name' => $this->branch->name,
                ];
            }),
            'order_number' => $this->order_number,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'payment_amount' => $this->payment_amount,
            'remaining_payment' => $this->remaining_payment,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

