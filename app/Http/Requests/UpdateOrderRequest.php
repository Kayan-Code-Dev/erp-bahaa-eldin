<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\MySqlDateTime;

class UpdateOrderRequest extends FormRequest
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
        return [
            'delivery_date' => 'nullable|date|after_or_equal:today',
            'days_of_rent' => 'nullable|integer|min:1',
            'occasion_datetime' => ['nullable', new MySqlDateTime()],
            'replace_items' => 'nullable|array',
            'replace_items.*.old_cloth_id' => 'required_with:replace_items|integer|exists:clothes,id',
            'replace_items.*.new_cloth_id' => 'required_with:replace_items|integer|exists:clothes,id',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'delivery_date' => 'تاريخ التسليم',
            'days_of_rent' => 'أيام الإيجار',
            'occasion_datetime' => 'تاريخ المناسبة',
            'replace_items' => 'استبدال القطع',
            'replace_items.*.old_cloth_id' => 'معرف القطعة القديمة',
            'replace_items.*.new_cloth_id' => 'معرف القطعة الجديدة',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'replace_items.*.old_cloth_id.required_with' => 'معرف القطعة القديمة مطلوب',
            'replace_items.*.old_cloth_id.exists' => 'القطعة القديمة غير موجودة',
            'replace_items.*.new_cloth_id.required_with' => 'معرف القطعة الجديدة مطلوب',
            'replace_items.*.new_cloth_id.exists' => 'القطعة الجديدة غير موجودة',
        ];
    }
}

