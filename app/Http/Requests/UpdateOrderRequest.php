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
            'visit_datetime' => ['nullable', new MySqlDateTime()],
            'delivery_date' => 'nullable|date|after_or_equal:today',
            'days_of_rent' => 'nullable|integer|min:1',
            'occasion_datetime' => ['nullable', new MySqlDateTime()],
            'replace_items' => 'nullable|array',
            'replace_items.*.old_cloth_id' => 'required_with:replace_items|integer|exists:clothes,id',
            'replace_items.*.new_cloth_id' => 'required_with:replace_items|integer|exists:clothes,id',
            // Item parameters (same as store order, except type which is inherited)
            'replace_items.*.price' => 'nullable|numeric|min:0',
            'replace_items.*.quantity' => 'nullable|integer|min:1',
            'replace_items.*.paid' => 'nullable|numeric|min:0',
            'replace_items.*.notes' => 'nullable|string',
            'replace_items.*.discount_type' => 'nullable|string|in:percentage,fixed',
            'replace_items.*.discount_value' => 'required_with:replace_items.*.discount_type|nullable|numeric|gt:0',
            // Measurements
            'replace_items.*.sleeve_length' => 'nullable|string|max:50',
            'replace_items.*.forearm' => 'nullable|string|max:50',
            'replace_items.*.shoulder_width' => 'nullable|string|max:50',
            'replace_items.*.cuffs' => 'nullable|string|max:50',
            'replace_items.*.waist' => 'nullable|string|max:50',
            'replace_items.*.chest_length' => 'nullable|string|max:50',
            'replace_items.*.total_length' => 'nullable|string|max:50',
            'replace_items.*.hinch' => 'nullable|string|max:50',
            'replace_items.*.dress_size' => 'nullable|string|max:50',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'visit_datetime' => 'موعد الزيارة',
            'delivery_date' => 'تاريخ التسليم',
            'days_of_rent' => 'أيام الإيجار',
            'occasion_datetime' => 'تاريخ المناسبة',
            'replace_items' => 'استبدال القطع',
            'replace_items.*.old_cloth_id' => 'معرف القطعة القديمة',
            'replace_items.*.new_cloth_id' => 'معرف القطعة الجديدة',
            'replace_items.*.price' => 'السعر',
            'replace_items.*.quantity' => 'الكمية',
            'replace_items.*.paid' => 'المبلغ المدفوع',
            'replace_items.*.notes' => 'الملاحظات',
            'replace_items.*.discount_type' => 'نوع الخصم',
            'replace_items.*.discount_value' => 'قيمة الخصم',
            'replace_items.*.sleeve_length' => 'طول الكم',
            'replace_items.*.forearm' => 'الزند',
            'replace_items.*.shoulder_width' => 'عرض الكتف',
            'replace_items.*.cuffs' => 'الإسوار',
            'replace_items.*.waist' => 'الوسط',
            'replace_items.*.chest_length' => 'طول الصدر',
            'replace_items.*.total_length' => 'الطول الكلي',
            'replace_items.*.hinch' => 'الهش',
            'replace_items.*.dress_size' => 'مقاس الفستان',
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
            'replace_items.*.price.min' => 'السعر يجب أن يكون أكبر من أو يساوي صفر',
            'replace_items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
            'replace_items.*.paid.min' => 'المبلغ المدفوع يجب أن يكون أكبر من أو يساوي صفر',
            'replace_items.*.discount_value.required_with' => 'قيمة الخصم مطلوبة عند تحديد نوع الخصم',
            'replace_items.*.discount_value.gt' => 'قيمة الخصم يجب أن تكون أكبر من صفر',
        ];
    }
}

