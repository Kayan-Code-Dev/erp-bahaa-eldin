<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\SupplierOrder;

class SupplierOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $orderId = $this->route('id');

        $rules = [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'exists:subcategories,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'order_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('supplier_orders', 'order_number')->ignore($orderId),
            ],
            'order_date' => ['required', 'date'],
            'status' => ['sometimes', 'string', Rule::in(array_keys(SupplierOrder::getStatuses()))],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'remaining_payment' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];

        // For update requests, make fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['supplier_id'][0] = 'sometimes';
            $rules['order_number'][0] = 'sometimes';
            $rules['order_date'][0] = 'sometimes';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'The supplier is required.',
            'supplier_id.exists' => 'The selected supplier does not exist.',
            'order_number.required' => 'The order number is required.',
            'order_number.unique' => 'This order number is already in use.',
            'order_date.required' => 'The order date is required.',
        ];
    }
}

