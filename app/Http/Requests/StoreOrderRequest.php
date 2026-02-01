<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\MySqlDateTime;

class StoreOrderRequest extends FormRequest
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
            // Order fields (paid is calculated from items, not sent directly)
            'client_id' => 'required|exists:clients,id',
            'entity_type' => 'required|string|in:branch,workshop,factory',
            'entity_id' => 'required|integer',
            'visit_datetime' => ['nullable', new MySqlDateTime()],
            'order_notes' => 'nullable|string',
            'discount_type' => 'nullable|string|in:percentage,fixed', // خصم على مستوى الطلب
            'discount_value' => 'required_with:discount_type|nullable|numeric|gt:0',

            // Items array
            'items' => 'required|array|min:1',
            'items.*.cloth_id' => 'required|integer|exists:clothes,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'nullable|integer|min:1', // الكمية
            'items.*.paid' => 'nullable|numeric|min:0', // المبلغ المدفوع لكل قطعة
            'items.*.type' => 'required|string|in:buy,rent,tailoring',
            'items.*.days_of_rent' => 'required_if:items.*.type,rent|nullable|integer|min:1',
            'items.*.occasion_datetime' => ['required_if:items.*.type,rent', 'nullable', new MySqlDateTime()],
            'items.*.delivery_date' => 'required_if:items.*.type,rent|nullable|date|after_or_equal:today',
            'items.*.notes' => 'nullable|string',
            'items.*.discount_type' => 'nullable|string|in:percentage,fixed',
            'items.*.discount_value' => 'required_with:items.*.discount_type|nullable|numeric|gt:0',

            // Measurements (مقاسات)
            'items.*.sleeve_length' => 'nullable|string|max:50', // طول الكم
            'items.*.forearm' => 'nullable|string|max:50', // الزند
            'items.*.shoulder_width' => 'nullable|string|max:50', // عرض الكتف
            'items.*.cuffs' => 'nullable|string|max:50', // الإسوار
            'items.*.waist' => 'nullable|string|max:50', // الوسط
            'items.*.chest_length' => 'nullable|string|max:50', // طول الصدر
            'items.*.total_length' => 'nullable|string|max:50', // الطول الكلي
            'items.*.hinch' => 'nullable|string|max:50', // الهش
            'items.*.dress_size' => 'nullable|string|max:50', // مقاس الفستان
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'العميل',
            'entity_type' => 'نوع الكيان',
            'entity_id' => 'معرف الكيان',
            'visit_datetime' => 'تاريخ الزيارة',
            'order_notes' => 'ملاحظات الطلب',
            'discount_type' => 'نوع الخصم',
            'discount_value' => 'قيمة الخصم',
            'items' => 'العناصر',
            'items.*.cloth_id' => 'الملبس',
            'items.*.price' => 'السعر',
            'items.*.quantity' => 'الكمية',
            'items.*.paid' => 'المبلغ المدفوع',
            'items.*.type' => 'نوع الطلب',
            'items.*.days_of_rent' => 'أيام الإيجار',
            'items.*.occasion_datetime' => 'تاريخ المناسبة',
            'items.*.delivery_date' => 'تاريخ التسليم',
            'items.*.notes' => 'الملاحظات',
            'items.*.sleeve_length' => 'طول الكم',
            'items.*.forearm' => 'الزند',
            'items.*.shoulder_width' => 'عرض الكتف',
            'items.*.cuffs' => 'الإسوار',
            'items.*.waist' => 'الوسط',
            'items.*.chest_length' => 'طول الصدر',
            'items.*.total_length' => 'الطول الكلي',
            'items.*.hinch' => 'الهش',
            'items.*.dress_size' => 'مقاس الفستان',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'العميل مطلوب',
            'client_id.exists' => 'العميل غير موجود',
            'entity_type.required' => 'نوع الكيان مطلوب',
            'entity_type.in' => 'نوع الكيان يجب أن يكون branch أو workshop أو factory',
            'entity_id.required' => 'معرف الكيان مطلوب',
            'items.required' => 'يجب إضافة عنصر واحد على الأقل',
            'items.min' => 'يجب إضافة عنصر واحد على الأقل',
            'items.*.cloth_id.required' => 'الملبس مطلوب',
            'items.*.cloth_id.exists' => 'الملبس غير موجود',
            'items.*.price.required' => 'السعر مطلوب',
            'items.*.price.min' => 'السعر يجب أن يكون أكبر من أو يساوي صفر',
            'items.*.quantity.min' => 'الكمية يجب أن تكون 1 على الأقل',
            'items.*.type.required' => 'نوع الطلب مطلوب',
            'items.*.type.in' => 'نوع الطلب يجب أن يكون buy أو rent أو tailoring',
            'items.*.days_of_rent.required_if' => 'أيام الإيجار مطلوبة للإيجار',
            'items.*.occasion_datetime.required_if' => 'تاريخ المناسبة مطلوب للإيجار',
            'items.*.delivery_date.required_if' => 'تاريخ التسليم مطلوب للإيجار',
            'items.*.delivery_date.after_or_equal' => 'تاريخ التسليم يجب أن يكون اليوم أو بعده',
        ];
    }
}

