<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCustomerMarketer() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:users,phone|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'email' => 'nullable|email|unique:users,email',
            'address' => 'nullable|string|max:500',
            'password' => 'required|string|min:6', 
            'identity_image_base64' => 'nullable|string',
            'personal_image_base64' => 'nullable|string',
            'fingerprint_data' => 'nullable|json'
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
            'identity_image_base64.string' => 'صورة الهوية يجب أن تكون نص مشفر',
            'personal_image_base64.string' => 'الصورة الشخصية يجب أن تكون نص مشفر',
            'fingerprint_data.json' => 'بيانات البصمة غير صالحة'
        ];
    }
}