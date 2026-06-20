<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FileUploadController extends Controller
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * @OA\Post(
     *     path="/api/upload/identity",
     *     summary="رفع صورة الهوية",
     *     tags={"Upload"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function uploadIdentityImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);
        
        $path = $this->fileUploadService->uploadIdentityImage($request->file('image'));
        
        return response()->json([
            'success' => true,
            'message' => 'تم رفع صورة الهوية بنجاح',
            'data' => [
                'path' => $path,
                'url' => asset('storage/' . $path)
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/upload/personal",
     *     summary="رفع الصورة الشخصية",
     *     tags={"Upload"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function uploadPersonalImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);
        
        $path = $this->fileUploadService->uploadPersonalImage($request->file('image'));
        
        return response()->json([
            'success' => true,
            'message' => 'تم رفع الصورة الشخصية بنجاح',
            'data' => [
                'path' => $path,
                'url' => asset('storage/' . $path)
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/upload/contract",
     *     summary="رفع عقد المؤسسة",
     *     tags={"Upload"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function uploadContract(Request $request): JsonResponse
    {
        $request->validate([
            'contract' => 'required|file|mimes:pdf,doc,docx|max:5120'
        ]);
        
        $path = $this->fileUploadService->uploadContract($request->file('contract'));
        
        return response()->json([
            'success' => true,
            'message' => 'تم رفع العقد بنجاح',
            'data' => [
                'path' => $path,
                'url' => asset('storage/' . $path)
            ]
        ]);
    }
}