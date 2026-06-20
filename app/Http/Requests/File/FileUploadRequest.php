<?php

namespace App\Http\Requests\File;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->route('type');
        
        $rules = [
            'file' => 'required|file|max:5120'
        ];
        
        switch ($type) {
            case 'identity':
            case 'personal':
                $rules['file'] .= '|image|mimes:jpeg,png,jpg|max:2048';
                break;
            case 'contract':
                $rules['file'] .= '|mimes:pdf,doc,docx|max:5120';
                break;
        }
        
        return $rules;
    }

    public function messages(): array
    {
        return [
            'file.required' => 'الملف مطلوب',
            'file.file' => 'الملف غير صحيح',
            'file.image' => 'الملف يجب أن يكون صورة',
            'file.mimes' => 'نوع الملف غير مدعوم',
            'file.max' => 'حجم الملف كبير جداً'
        ];
    }
}