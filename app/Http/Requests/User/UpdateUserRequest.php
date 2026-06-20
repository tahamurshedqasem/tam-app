<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $user = $this->route('user');
        
        return [
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|unique:users,phone,' . $user->id . '|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|in:admin,customer_marketer,institution_marketer',
            'status' => 'sometimes|in:active,inactive,suspended'
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'رقم الهاتف مسجل بالفعل',
            'phone.regex' => 'صيغة رقم الهاتف غير صحيحة',
            'password.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'role.in' => 'الدور غير صحيح',
            'status.in' => 'الحالة غير صحيحة'
        ];
    }
}