<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');
        return $this->user()->isAdmin() || 
               $this->user()->isCustomerMarketer() || 
               $this->user()->id === $customer->user_id;
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        
        return [
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|unique:users,phone,' . ($customer->user_id ?? 'NULL') . '|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'address' => 'nullable|string|max:500',
            'identity_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'personal_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'fingerprint_data' => 'nullable|json',
            'status' => 'sometimes|in:active,inactive,suspended'
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'رقم الهاتف مسجل بالفعل',
            'phone.regex' => 'صيغة رقم الهاتف غير صحيحة',
            'identity_image.image' => 'صورة الهوية يجب أن تكون صورة',
            'personal_image.image' => 'الصورة الشخصية يجب أن تكون صورة',
            'status.in' => 'الحالة غير صحيحة'
        ];
    }
}