<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:users,phone|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,customer_marketer,institution_marketer',
            'status' => 'sometimes|in:active,inactive,suspended'
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'الاسم الكامل مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مسجل بالفعل',
            'phone.regex' => 'صيغة رقم الهاتف غير صحيحة',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'role.required' => 'الدور مطلوب',
            'role.in' => 'الدور غير صحيح',
            'status.in' => 'الحالة غير صحيحة'
        ];
    }
}