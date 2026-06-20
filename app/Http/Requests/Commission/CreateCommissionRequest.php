<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:customer_marketer,institution_marketer',
            'amount' => 'required|numeric|min:0',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'reason' => 'required|string|max:500',
            'transaction_id' => 'nullable|exists:discount_transactions,id'
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'معرف المستخدم مطلوب',
            'user_id.exists' => 'المستخدم غير موجود',
            'role.required' => 'الدور مطلوب',
            'role.in' => 'الدور غير صحيح',
            'amount.required' => 'المبلغ مطلوب',
            'amount.numeric' => 'المبلغ يجب أن يكون رقماً',
            'amount.min' => 'المبلغ لا يمكن أن يكون سالباً',
            'commission_percentage.required' => 'نسبة العمولة مطلوبة',
            'commission_percentage.numeric' => 'نسبة العمولة يجب أن تكون رقماً',
            'commission_percentage.min' => 'نسبة العمولة لا يمكن أن تكون أقل من 0',
            'commission_percentage.max' => 'نسبة العمولة لا يمكن أن تزيد عن 100',
            'reason.required' => 'السبب مطلوب'
        ];
    }
}