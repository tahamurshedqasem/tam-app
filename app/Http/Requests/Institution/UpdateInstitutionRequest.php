<?php

namespace App\Http\Requests\Institution;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $institution = $this->route('institution');
        return $this->user()->isAdmin() || 
               $this->user()->isInstitutionMarketer() ||
               $this->user()->id === $institution->owner_id;
    }

    public function rules(): array
    {
        $institution = $this->route('institution');
        
        return [
            'name' => 'sometimes|string|max:255',
            'type_id' => 'sometimes|exists:institution_types,id',
            'phone' => 'sometimes|string|unique:institutions,phone,' . $institution->id . '|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'email' => 'nullable|email|unique:institutions,email,' . $institution->id,
            'address' => 'sometimes|string|max:500',
            'discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'contract_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'agreement_date' => 'sometimes|date',
            'agreement_expiry_date' => 'nullable|date|after:agreement_date',
            'status' => 'sometimes|in:active,inactive,expired',
            'description' => 'nullable|string|max:1000',
            'business_hours' => 'nullable|json',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'رقم الهاتف مسجل بالفعل',
            'email.unique' => 'البريد الإلكتروني مسجل بالفعل',
            'discount_percentage.numeric' => 'نسبة الخصم يجب أن تكون رقماً',
            'discount_percentage.min' => 'نسبة الخصم لا يمكن أن تكون أقل من 0',
            'discount_percentage.max' => 'نسبة الخصم لا يمكن أن تزيد عن 100',
            'status.in' => 'الحالة غير صحيحة',
            'agreement_expiry_date.after' => 'تاريخ انتهاء الاتفاقية يجب أن يكون بعد تاريخ الاتفاقية'
        ];
    }
}