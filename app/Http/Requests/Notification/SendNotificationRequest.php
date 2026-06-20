<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required_without:role|exists:users,id',
            'role' => 'required_without:user_id|in:admin,customer,customer_marketer,institution_marketer,institution_owner',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:1000',
            'type' => 'sometimes|in:info,success,warning,error',
            'data' => 'nullable|array'
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان الإشعار مطلوب',
            'body.required' => 'محتوى الإشعار مطلوب',
            'type.in' => 'نوع الإشعار غير صحيح',
            'user_id.exists' => 'المستخدم غير موجود'
        ];
    }
}