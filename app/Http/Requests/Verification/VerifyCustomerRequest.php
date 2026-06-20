<?php

namespace App\Http\Requests\Verification;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isInstitutionOwner() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'membership_number' => 'required|string|exists:customers,membership_number',
            'institution_id' => 'required|exists:institutions,id'
        ];
    }

    public function messages(): array
    {
        return [
            'membership_number.required' => 'رقم العضوية مطلوب',
            'membership_number.exists' => 'رقم العضوية غير صحيح',
            'institution_id.required' => 'معرف المؤسسة مطلوب',
            'institution_id.exists' => 'المؤسسة غير موجودة'
        ];
    }
}