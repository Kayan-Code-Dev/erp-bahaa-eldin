<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnClothRequest extends FormRequest
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
            'entity_type' => 'required|in:branch,workshop,factory',
            'entity_id' => 'required|integer',
            'note' => 'required|string',
            'photos' => 'required|array|min:1|max:10',
            'photos.*' => 'required|image|mimes:jpeg,png,gif,webp,bmp|max:5120',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'entity_type' => 'نوع الكيان',
            'entity_id' => 'معرف الكيان',
            'note' => 'الملاحظات',
            'photos' => 'الصور',
            'photos.*' => 'الصورة',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'entity_type.required' => 'نوع الكيان مطلوب',
            'entity_type.in' => 'نوع الكيان يجب أن يكون branch أو workshop أو factory',
            'entity_id.required' => 'معرف الكيان مطلوب',
            'entity_id.integer' => 'معرف الكيان يجب أن يكون رقم',
            'note.required' => 'الملاحظات مطلوبة',
            'photos.required' => 'يجب إرفاق صورة واحدة على الأقل',
            'photos.min' => 'يجب إرفاق صورة واحدة على الأقل',
            'photos.max' => 'الحد الأقصى 10 صور',
            'photos.*.image' => 'الملف يجب أن يكون صورة',
            'photos.*.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, gif, webp, bmp',
            'photos.*.max' => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت',
        ];
    }
}

