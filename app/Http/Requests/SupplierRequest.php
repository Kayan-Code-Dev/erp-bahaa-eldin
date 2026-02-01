<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
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
        $supplierId = $this->route('id');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('suppliers', 'code')->ignore($supplierId),
            ],
        ];

        // For update requests, make fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'][0] = 'sometimes';
            $rules['code'][0] = 'sometimes';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The supplier name is required.',
            'code.required' => 'The supplier code is required.',
            'code.unique' => 'This supplier code is already in use.',
        ];
    }
}

