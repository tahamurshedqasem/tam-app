<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/auth/me
     * ✅ جلب بيانات المستخدم الحالي (لجميع الأدوار)
     */
    public function getUserProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $userData = [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'phone' => $user->phone,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'region' => $user->region,
                'total_commission' => (float) ($user->total_commission ?? 0),
                'pending_commission' => (float) ($user->pending_commission ?? 0),
                'paid_commission' => (float) ($user->paid_commission ?? 0),
                'customers_count' => (int) ($user->customers_count ?? 0),
                'institutions_count' => (int) ($user->institutions_count ?? 0),
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
            ];

            // ✅ إضافة بيانات العميل إذا كان المستخدم عميلاً
            if ($user->role === 'customer') {
                $customer = Customer::where('user_id', $user->id)->first();
                if ($customer) {
                    $userData['customer'] = [
                        'id' => $customer->id,
                        'membership_number' => $customer->membership_number,
                        'address' => $customer->address,
                        'membership_status' => $customer->membership_status,
                        'membership_expiry_date' => $customer->membership_expiry_date?->toISOString(),
                        'total_discount_saved' => (float) ($customer->total_discount_saved ?? 0),
                        'has_fingerprint' => $customer->hasFingerprint(),
                        'created_at' => $customer->created_at?->toISOString(),
                    ];
                    $userData['membership_number'] = $customer->membership_number;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $userData
            ]);
        } catch (\Exception $e) {
            Log::error('Get user profile error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customers/me
     * ✅ جلب بيانات العميل الحالي (للمستخدمين من نوع Customer فقط)
     */
    public function getCustomerProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            // ✅ التحقق من أن المستخدم عميل
            if ($user->role !== 'customer') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم ليس عميلاً',
                    'data' => null
                ], 200); // ✅ نعيد 200 مع رسالة بدلاً من 403
            }

            $customer = Customer::with(['user', 'discountTransactions'])
                ->where('user_id', $user->id)
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات العميل غير موجودة',
                    'data' => null
                ], 404);
            }

            // ✅ تحويل القيم بشكل صحيح
            $totalTransactions = $customer->discountTransactions()->count();
            $totalSavings = (float) $customer->discountTransactions()->sum('amount_saved');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $customer->id,
                    'user_id' => $customer->user_id,
                    'full_name' => $customer->user?->full_name ?? '',
                    'phone' => $customer->user?->phone ?? '',
                    'email' => $customer->user?->email ?? '',
                    'membership_number' => $customer->membership_number,
                    'address' => $customer->address,
                    'membership_status' => $customer->membership_status,
                    'membership_expiry_date' => $customer->membership_expiry_date?->toISOString(),
                    'total_discount_saved' => (float) ($customer->total_discount_saved ?? 0),
                    'total_discount_usage' => (int) $totalTransactions,
                    'total_savings' => (float) $totalSavings,
                    'has_fingerprint' => $customer->hasFingerprint(),
                    'days_remaining' => (int) ($customer->days_remaining ?? 0),
                    'identity_image' => $customer->identity_image,
                    'personal_image' => $customer->personal_image,
                    'fingerprint_data' => $customer->fingerprint_data,
                    'created_by_marketer' => $customer->created_by_marketer,
                    'created_at' => $customer->created_at?->toISOString(),
                    'updated_at' => $customer->updated_at?->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get customer profile error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/customer/profile
     * ✅ تحديث الملف الشخصي (للمستخدمين من نوع Customer فقط)
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            // ✅ التحقق من أن المستخدم عميل
            if ($user->role !== 'customer') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم ليس عميلاً'
                ], 403);
            }

            $validated = $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'phone' => [
                    'sometimes',
                    'string',
                    'regex:/^([0-9\s\-\+\(\)]*)$/',
                    'min:10',
                    'max:15',
                    Rule::unique('users', 'phone')->ignore($user->id),
                ],
                'email' => [
                    'nullable',
                    'email',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'address' => 'nullable|string|max:500',
            ]);

            // ✅ تحديث بيانات المستخدم
            $userData = [];
            if (isset($validated['full_name'])) {
                $userData['full_name'] = $validated['full_name'];
            }
            if (isset($validated['phone'])) {
                $userData['phone'] = $validated['phone'];
            }
            if (isset($validated['email'])) {
                $userData['email'] = $validated['email'];
            }

            if (!empty($userData)) {
                $user->update($userData);
            }

            // ✅ تحديث بيانات العميل
            $customer = Customer::where('user_id', $user->id)->first();
            if ($customer && isset($validated['address'])) {
                $customer->update(['address' => $validated['address']]);
            }

            // ✅ جلب البيانات المحدثة
            $updatedCustomer = Customer::with('user')
                ->where('user_id', $user->id)
                ->first();

            Log::info('Profile updated for user: ' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الملف الشخصي بنجاح',
                'data' => [
                    'id' => $updatedCustomer?->id,
                    'full_name' => $user->full_name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'address' => $updatedCustomer?->address,
                    'membership_number' => $updatedCustomer?->membership_number,
                    'updated_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Update profile error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/customer/change-password
     * ✅ تغيير كلمة المرور (لجميع المستخدمين)
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|different:current_password',
                'confirm_password' => 'required|string|same:new_password',
            ]);

            // ✅ التحقق من كلمة المرور الحالية
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'كلمة المرور الحالية غير صحيحة'
                ], 422);
            }

            // ✅ تحديث كلمة المرور
            $user->password = Hash::make($validated['new_password']);
            $user->save();

            Log::info('Password changed for user: ' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Change password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/customer/fingerprint
     * ✅ تسجيل بصمة العميل
     */
    public function storeFingerprint(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            if ($user->role !== 'customer') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم ليس عميلاً'
                ], 403);
            }

            $validated = $request->validate([
                'fingerprint_data' => 'required|array',
            ]);

            $customer = Customer::where('user_id', $user->id)->first();
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات العميل غير موجودة'
                ], 404);
            }

            // ✅ معالجة بيانات البصمة
            $fingerprintData = [
                'data' => $validated['fingerprint_data'],
                'registered_at' => now()->toISOString(),
                'device_info' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ];

            $customer->fingerprint_data = $fingerprintData;
            $customer->save();

            Log::info('Fingerprint registered for customer: ' . $customer->id);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل البصمة بنجاح',
                'data' => [
                    'has_fingerprint' => true,
                    'registered_at' => now()->toISOString(),
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Store fingerprint error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/customer/fingerprint
     * ✅ حذف بصمة العميل
     */
    public function deleteFingerprint(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            if ($user->role !== 'customer') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم ليس عميلاً'
                ], 403);
            }

            $customer = Customer::where('user_id', $user->id)->first();
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات العميل غير موجودة'
                ], 404);
            }

            $customer->fingerprint_data = null;
            $customer->save();

            Log::info('Fingerprint deleted for customer: ' . $customer->id);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف البصمة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete fingerprint error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer/stats
     * ✅ إحصائيات العميل
     */
    public function getCustomerStats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            if ($user->role !== 'customer') {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم ليس عميلاً'
                ], 403);
            }

            $customer = Customer::with(['discountTransactions'])
                ->where('user_id', $user->id)
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات العميل غير موجودة'
                ], 404);
            }

            $totalTransactions = $customer->discountTransactions()->count();
            $totalSavings = (float) $customer->discountTransactions()->sum('amount_saved');
            $lastTransaction = $customer->discountTransactions()->latest()->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_savings' => (float) $totalSavings,
                    'total_transactions' => (int) $totalTransactions,
                    'membership_status' => $customer->membership_status,
                    'days_remaining' => (int) ($customer->days_remaining ?? 0),
                    'membership_expiry_date' => $customer->membership_expiry_date?->toISOString(),
                    'has_fingerprint' => $customer->hasFingerprint(),
                    'loyalty_points' => (int) ($totalTransactions * 10),
                    'last_transaction_date' => $lastTransaction?->transaction_date?->toISOString(),
                    'last_transaction_amount' => (float) ($lastTransaction?->amount_saved ?? 0),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get customer stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}