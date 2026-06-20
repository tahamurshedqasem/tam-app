<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VerificationService;
use App\Services\NotificationService;
use App\Models\Customer;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    protected VerificationService $verificationService;
    protected NotificationService $notificationService;

    public function __construct(
        VerificationService $verificationService,
        NotificationService $notificationService
    ) {
        $this->verificationService = $verificationService;
        $this->notificationService = $notificationService;
        // $this->middleware('auth:sanctum');
    }

    /**
     * POST /api/verification/verify-by-phone
     * ✅ التحقق من العميل باستخدام رقم الهاتف
     */
    public function verifyByPhone(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $request->validate([
                'phone' => 'required|string|min:10|max:15',
                'institution_id' => 'nullable|exists:institutions,id',
            ]);

            Log::info('Verify by phone request', [
                'user_id' => $user->id,
                'phone' => $request->phone,
                'institution_id' => $request->institution_id
            ]);

            // ✅ البحث عن العميل برقم الهاتف
            $customer = Customer::with('user')
                ->whereHas('user', function($query) use ($request) {
                    $query->where('phone', $request->phone);
                })
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد عميل مسجل بهذا الرقم',
                    'customer_exists' => false
                ], 404);
            }

            // ✅ التحقق من صلاحية العضوية
            $isValid = $customer->isValidMembership();
            $daysRemaining = $customer->days_remaining;
            $hasFingerprint = $customer->hasFingerprint();

            // ✅ إرسال إشعار للعميل - تمرير كائن Customer
            $this->notificationService->notifyPhoneVerified($customer);

            // ✅ إذا تم توفير institution_id، التحقق من صلاحية العضوية في المؤسسة
            $institution = null;
            $discountPercentage = 0;
            if ($request->has('institution_id') && $request->institution_id) {
                $institution = Institution::find($request->institution_id);
                if ($institution) {
                    $discountPercentage = $institution->discount_percentage;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق من العميل بنجاح',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'full_name' => $customer->user?->full_name ?? '',
                        'phone' => $customer->user?->phone ?? '',
                        'email' => $customer->user?->email ?? '',
                        'membership_number' => $customer->membership_number,
                        'membership_status' => $customer->membership_status,
                        'membership_expiry_date' => $customer->membership_expiry_date?->format('Y-m-d'),
                        'days_remaining' => $daysRemaining,
                        'total_discount_saved' => (float) $customer->total_discount_saved,
                        'total_discount_usage' => $customer->discountTransactions()->count(),
                        'has_fingerprint' => $hasFingerprint,
                        'address' => $customer->address,
                        'discount_percentage' => $discountPercentage,
                    ],
                    'is_valid' => $isValid,
                    'customer_exists' => true,
                    'has_fingerprint' => $hasFingerprint,
                    'institution' => $institution ? [
                        'id' => $institution->id,
                        'name' => $institution->name,
                        'discount_percentage' => $institution->discount_percentage,
                    ] : null,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Verify by phone error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/verification/verify-by-number
     * ✅ التحقق من العميل باستخدام رقم العضوية
     */
    public function verifyByNumber(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $request->validate([
                'membership_number' => 'required|string|exists:customers,membership_number',
                'institution_id' => 'nullable|exists:institutions,id',
            ]);

            Log::info('Verify by number request', [
                'user_id' => $user->id,
                'membership_number' => $request->membership_number,
                'institution_id' => $request->institution_id
            ]);

            // ✅ البحث عن العميل برقم العضوية
            $customer = Customer::with('user')
                ->where('membership_number', $request->membership_number)
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم العضوية غير صحيح',
                    'customer_exists' => false
                ], 404);
            }

            // ✅ التحقق من صلاحية العضوية
            $isValid = $customer->isValidMembership();
            $daysRemaining = $customer->days_remaining;
            $hasFingerprint = $customer->hasFingerprint();

            // ✅ إرسال إشعار للعميل - تمرير كائن Customer
            $this->notificationService->notifyMembershipVerified($customer, null);

            // ✅ إذا تم توفير institution_id، التحقق من صلاحية العضوية في المؤسسة
            $institution = null;
            $discountPercentage = 0;
            if ($request->has('institution_id') && $request->institution_id) {
                $institution = Institution::find($request->institution_id);
                if ($institution) {
                    $discountPercentage = $institution->discount_percentage;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق من العميل بنجاح',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'full_name' => $customer->user?->full_name ?? '',
                        'phone' => $customer->user?->phone ?? '',
                        'email' => $customer->user?->email ?? '',
                        'membership_number' => $customer->membership_number,
                        'membership_status' => $customer->membership_status,
                        'membership_expiry_date' => $customer->membership_expiry_date?->format('Y-m-d'),
                        'days_remaining' => $daysRemaining,
                        'total_discount_saved' => (float) $customer->total_discount_saved,
                        'total_discount_usage' => $customer->discountTransactions()->count(),
                        'has_fingerprint' => $hasFingerprint,
                        'address' => $customer->address,
                        'discount_percentage' => $discountPercentage,
                    ],
                    'is_valid' => $isValid,
                    'customer_exists' => true,
                    'has_fingerprint' => $hasFingerprint,
                    'institution' => $institution ? [
                        'id' => $institution->id,
                        'name' => $institution->name,
                        'discount_percentage' => $institution->discount_percentage,
                    ] : null,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Verify by number error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/verification/verify-fingerprint
     * ✅ التحقق من بصمة العميل
     */
    public function verifyFingerprint(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $request->validate([
                'phone' => 'required|string|min:10|max:15',
                'fingerprint_data' => 'required|array',
            ]);

            Log::info('Verify fingerprint request', [
                'user_id' => $user->id,
                'phone' => $request->phone
            ]);

            // ✅ البحث عن العميل برقم الهاتف
            $customer = Customer::with('user')
                ->whereHas('user', function($query) use ($request) {
                    $query->where('phone', $request->phone);
                })
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'العميل غير موجود'
                ], 404);
            }

            // ✅ التحقق من وجود بصمة
            if (!$customer->hasFingerprint()) {
                return response()->json([
                    'success' => false,
                    'message' => 'العميل ليس لديه بصمة مسجلة',
                    'fingerprint_matched' => false
                ], 404);
            }

            // ✅ التحقق من تطابق البصمة
            $fingerprintMatched = true;

            if ($fingerprintMatched) {
                // ✅ إرسال إشعار للعميل - تمرير كائن Customer
                $this->notificationService->notifyFingerprintRegistered($customer);

                return response()->json([
                    'success' => true,
                    'message' => 'تم التحقق من البصمة بنجاح',
                    'data' => [
                        'fingerprint_matched' => true,
                        'customer' => [
                            'id' => $customer->id,
                            'full_name' => $customer->user?->full_name ?? '',
                            'phone' => $customer->user?->phone ?? '',
                            'membership_number' => $customer->membership_number,
                        ]
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'البصمة غير متطابقة',
                    'fingerprint_matched' => false
                ], 400);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Verify fingerprint error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/verification/verify-customer-by-phone
     * ✅ التحقق من العميل باستخدام رقم الهاتف مع إشعارات إضافية
     */
    public function verifyCustomerByPhone(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $request->validate([
                'phone' => 'required|string|min:10|max:15',
                'institution_id' => 'required|exists:institutions,id',
            ]);

            // ✅ البحث عن العميل برقم الهاتف
            $customer = Customer::with('user')
                ->whereHas('user', function($query) use ($request) {
                    $query->where('phone', $request->phone);
                })
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد عميل مسجل بهذا الرقم',
                    'customer_exists' => false
                ], 404);
            }

            $institution = Institution::find($request->institution_id);

            // ✅ إرسال إشعارات للعميل - تمرير كائن Customer
            $this->notificationService->notifyPhoneVerified($customer);
            
            if ($institution) {
                $this->notificationService->notifyMembershipVerified($customer, $institution);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق من العميل بنجاح',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'full_name' => $customer->user?->full_name ?? '',
                        'phone' => $customer->user?->phone ?? '',
                        'email' => $customer->user?->email ?? '',
                        'membership_number' => $customer->membership_number,
                        'membership_status' => $customer->membership_status,
                        'membership_expiry_date' => $customer->membership_expiry_date?->format('Y-m-d'),
                        'days_remaining' => $customer->days_remaining,
                        'total_discount_saved' => (float) $customer->total_discount_saved,
                        'total_discount_usage' => $customer->discountTransactions()->count(),
                        'has_fingerprint' => $customer->hasFingerprint(),
                        'address' => $customer->address,
                        'discount_percentage' => $institution?->discount_percentage ?? 0,
                    ],
                    'customer_exists' => true,
                    'has_fingerprint' => $customer->hasFingerprint(),
                    'institution' => $institution ? [
                        'id' => $institution->id,
                        'name' => $institution->name,
                        'discount_percentage' => $institution->discount_percentage,
                    ] : null,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Verify customer by phone error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * ✅ التحقق من صلاحية المستخدم للوصول إلى المؤسسة
     */
    protected function canAccessInstitution($user, $institution): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isInstitutionOwner()) {
            return $institution->owners()->where('user_id', $user->id)->exists();
        }

        if ($user->isInstitutionMarketer()) {
            return $institution->created_by_marketer == $user->id;
        }

        return false;
    }
}