<?php

namespace App\Http\Requests\Institution;

use Illuminate\Foundation\Http\FormRequest;

class CreateInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isInstitutionMarketer() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type_id' => 'required|exists:institution_types,id',
            'phone' => 'required|string|unique:institutions,phone|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
            'email' => 'nullable|email|unique:institutions,email',
            'address' => 'required|string|max:500',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'contract_file' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'agreement_date' => 'required|date',
            'agreement_expiry_date' => 'nullable|date|after:agreement_date',
            'description' => 'nullable|string|max:1000',
            'business_hours' => 'nullable|json',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'owner_name' => 'required|string|max:255',          // ✅ أضف هذا
            'owner_password' => 'required|string|min:6',   
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المؤسسة مطلوب',
            'type_id.required' => 'نوع المؤسسة مطلوب',
            'type_id.exists' => 'نوع المؤسسة غير صحيح',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.unique' => 'رقم الهاتف مسجل بالفعل',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'email.unique' => 'البريد الإلكتروني مسجل بالفعل',
            'address.required' => 'العنوان مطلوب',
            'discount_percentage.required' => 'نسبة الخصم مطلوبة',
            'discount_percentage.numeric' => 'نسبة الخصم يجب أن تكون رقماً',
            'discount_percentage.min' => 'نسبة الخصم لا يمكن أن تكون أقل من 0',
            'discount_percentage.max' => 'نسبة الخصم لا يمكن أن تزيد عن 100',
            'contract_file.mimes' => 'ملف العقد يجب أن يكون من نوع pdf, doc, docx',
            'contract_file.max' => 'حجم ملف العقد لا يتجاوز 5MB',
            'agreement_date.required' => 'تاريخ الاتفاقية مطلوب',
            'agreement_expiry_date.after' => 'تاريخ انتهاء الاتفاقية يجب أن يكون بعد تاريخ الاتفاقية'
        ];
    }
}