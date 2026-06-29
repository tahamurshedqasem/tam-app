<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="تسجيل الدخول",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone","password"},
     *             @OA\Property(property="phone", type="string", example="0500000000"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="تم تسجيل الدخول بنجاح")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => $result
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="تسجيل الخروج",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        
        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="بيانات المستخدم الحالي",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}}
     * )
     */
  public function me(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 404);
            }

            // ✅ إضافة بيانات إضافية حسب الدور
            $userData = $user->toArray();
            
            // إذا كان المستخدم عميلاً، جلب بيانات العميل
            if ($user->role === 'customer') {
                $customer = \App\Models\Customer::where('user_id', $user->id)->first();
                if ($customer) {
                    $userData['customer'] = $customer->toArray();
                    $userData['membership_number'] = $customer->membership_number;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $userData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/change-password",
     *     summary="تغيير كلمة المرور",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->current_password,
            $request->new_password
        );
        
        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }

    public function loginWithDevice(Request $request)
{
    $request->validate([
        'phone' => 'required|string',
        'password' => 'required|string',
        'device_id' => 'required|string',
    ]);

    $user = User::where('phone', $request->phone)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json([
            'success' => false,
            'message' => 'بيانات الدخول غير صحيحة'
        ], 401);
    }

    // ✅ التحقق من الجهاز
    if ($user->device_id && $user->device_id != $request->device_id) {
        return response()->json([
            'success' => false,
            'message' => 'هذا الحساب مسجل على جهاز آخر. لا يمكن تسجيل الدخول من أكثر من جهاز واحد.'
        ], 403);
    }

    // ✅ تحديث معرف الجهاز
    $user->device_id = $request->device_id;
    $user->save();

    // إنشاء التوكن
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'success' => true,
        'data' => [
            'user' => $user,
            'token' => $token,
            'role' => $user->role,
        ]
    ]);
}
}