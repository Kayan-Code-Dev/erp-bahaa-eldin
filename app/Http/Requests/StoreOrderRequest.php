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
            // Client selection: existing or new
            'existing_client' => 'required|boolean',

            // If existing client, require client_id
            'client_id' => 'required_if:existing_client,true|nullable|exists:clients,id',

            // If new client, require client data
            'client' => 'required_if:existing_client,false|nullable|array',
            'client.name' => 'required_if:existing_client,false|nullable|string|max:255',
            'client.national_id' => 'required_if:existing_client,false|nullable|string|digits:14|unique:clients,national_id',
            'client.date_of_birth' => 'nullable|date',
            'client.source' => 'nullable|string',
            'client.address' => 'required_if:existing_client,false|nullable|array',
            'client.address.city_id' => 'required_if:existing_client,false|nullable|exists:cities,id',
            'client.address.address' => 'required_if:existing_client,false|nullable|string|max:500',
            'client.phones' => 'required_if:existing_client,false|nullable|array|min:1',
            'client.phones.*.phone' => 'required_with:client.phones|string',
            'client.phones.*.type' => 'nullable|string|in:mobile,landline,whatsapp',
            // Client measurements (optional)
            'client.breast_size' => 'nullable|string|max:20',
            'client.waist_size' => 'nullable|string|max:20',
            'client.sleeve_size' => 'nullable|string|max:20',
            'client.hip_size' => 'nullable|string|max:20',
            'client.shoulder_size' => 'nullable|string|max:20',
            'client.length_size' => 'nullable|string|max:20',
            'client.measurement_notes' => 'nullable|string|max:1000',

            // Order fields
            'entity_type' => 'required|string|in:branch,workshop,factory',
            'entity_id' => 'required|integer',
            'delivery_date' => ['nullable', new MySqlDateTime()],

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
            'existing_client' => 'عميل موجود',
            'client_id' => 'معرف العميل',
            'client.name' => 'اسم العميل',
            'client.national_id' => 'الرقم القومي',
            'client.date_of_birth' => 'تاريخ الميلاد',
            'client.address.city_id' => 'المدينة',
            'client.address.address' => 'العنوان',
            'client.phones' => 'أرقام الهاتف',
            'entity_type' => 'نوع الكيان',
            'entity_id' => 'معرف الكيان',
            'delivery_date' => 'تاريخ التسليم',
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
            'existing_client.required' => 'يجب تحديد إذا كان العميل موجود أم جديد',
            'existing_client.boolean' => 'قيمة عميل موجود يجب أن تكون true أو false',
            'client_id.required_if' => 'معرف العميل مطلوب عند اختيار عميل موجود',
            'client_id.exists' => 'العميل غير موجود',
            'client.required_if' => 'بيانات العميل مطلوبة عند إنشاء عميل جديد',
            'client.name.required_if' => 'اسم العميل مطلوب',
            'client.national_id.required_if' => 'الرقم القومي مطلوب',
            'client.national_id.digits' => 'الرقم القومي يجب أن يكون 14 رقم',
            'client.national_id.unique' => 'الرقم القومي مستخدم بالفعل',
            'client.address.required_if' => 'العنوان مطلوب',
            'client.address.city_id.required_if' => 'المدينة مطلوبة',
            'client.address.address.required_if' => 'العنوان مطلوب',
            'client.phones.required_if' => 'رقم هاتف واحد على الأقل مطلوب',
            'client.phones.min' => 'رقم هاتف واحد على الأقل مطلوب',
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

