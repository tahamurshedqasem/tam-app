<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/jpg',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    protected int $maxFileSize = 5120; // 5MB

    public function uploadIdentityImage(UploadedFile $file): string
    {
        $this->validateFile($file, ['image/jpeg', 'image/png', 'image/jpg'], 2048);
        return $this->uploadFile($file, 'identities');
    }

    public function uploadPersonalImage(UploadedFile $file): string
    {
        $this->validateFile($file, ['image/jpeg', 'image/png', 'image/jpg'], 2048);
        return $this->uploadFile($file, 'profiles');
    }

    public function uploadContract(UploadedFile $file): string
    {
        $this->validateFile($file, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 5120);
        return $this->uploadFile($file, 'contracts');
    }

    public function uploadReceipt(UploadedFile $file): string
    {
        $this->validateFile($file, ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'], 2048);
        return $this->uploadFile($file, 'receipts');
    }

    protected function uploadFile(UploadedFile $file, string $directory): string
    {
        $fileName = $this->generateFileName($file);
        $path = $file->storeAs($directory, $fileName, 'public');
        
        if (!$path) {
            throw new \Exception('Failed to upload file');
        }
        
        return $path;
    }

    protected function generateFileName(UploadedFile $file): string
    {
        return Str::uuid() . '_' . time() . '.' . $file->getClientOriginalExtension();
    }

    protected function validateFile(UploadedFile $file, array $allowedMimes, int $maxSize): void
    {
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('نوع الملف غير مدعوم');
        }
        
        if ($file->getSize() > $maxSize * 1024) {
            throw new \Exception('حجم الملف كبير جداً');
        }
    }

    public function deleteFile(string $path): bool
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }
        
        return false;
    }

    public function getFileUrl(string $path): string
    {
        return asset('storage/' . $path);
    }
}