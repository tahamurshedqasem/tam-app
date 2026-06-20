<?php

namespace App\Http\Requests\Verification;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isInstitutionOwner() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'institution_id' => 'required|exists:institutions,id',
            'original_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500'
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'معرف العميل مطلوب',
            'customer_id.exists' => 'العميل غير موجود',
            'institution_id.required' => 'معرف المؤسسة مطلوب',
            'institution_id.exists' => 'المؤسسة غير موجودة',
            'original_amount.numeric' => 'المبلغ الأصلي يجب أن يكون رقماً',
            'original_amount.min' => 'المبلغ الأصلي لا يمكن أن يكون سالباً'
        ];
    }
}