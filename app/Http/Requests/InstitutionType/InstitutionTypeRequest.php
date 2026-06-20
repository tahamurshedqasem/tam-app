<?php

namespace App\Http\Requests\InstitutionType;

use Illuminate\Foundation\Http\FormRequest;

class InstitutionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $type = $this->route('institution_type');
        
        return [
            'name' => 'required|string|unique:institution_types,name,' . ($type->id ?? 'NULL') . '|max:100',
            'name_ar' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم النوع مطلوب',
            'name.unique' => 'اسم النوع مسجل بالفعل',
            'name.max' => 'اسم النوع لا يتجاوز 100 حرف',
            'is_active.boolean' => 'حالة التفعيل يجب أن تكون true/false'
        ];
    }
}