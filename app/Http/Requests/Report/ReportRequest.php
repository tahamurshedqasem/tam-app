<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'period' => 'sometimes|in:daily,weekly,monthly,yearly',
            'start_date' => 'nullable|date|before_or_equal:end_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string',
            'type_id' => 'nullable|exists:institution_types,id',
            'role' => 'nullable|in:customer_marketer,institution_marketer',
            'user_id' => 'nullable|exists:users,id',
            'marketer_id' => 'nullable|exists:users,id',
            'limit' => 'nullable|integer|min:1|max:100'
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' => 'الفترة غير صحيحة',
            'start_date.date' => 'تاريخ البداية غير صحيح',
            'end_date.date' => 'تاريخ النهاية غير صحيح',
            'start_date.before_or_equal' => 'تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية',
            'end_date.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد أو يساوي تاريخ البداية',
            'type_id.exists' => 'نوع المؤسسة غير موجود',
            'user_id.exists' => 'المستخدم غير موجود',
            'limit.integer' => 'الحد يجب أن يكون رقماً صحيحاً',
            'limit.min' => 'الحد يجب أن يكون على الأقل 1',
            'limit.max' => 'الحد لا يمكن أن يتجاوز 100'
        ];
    }
}