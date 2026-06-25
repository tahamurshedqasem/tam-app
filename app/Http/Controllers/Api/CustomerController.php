<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    protected CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
        // $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/customers
     * ✅ قائمة جميع العملاء مع التصفية والترتيب
     */
    public function index(Request $request)
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'marketer_id' => $request->get('marketer_id'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_direction' => $request->get('sort_direction', 'desc'),
            ];

            $perPage = $request->get('per_page', 15);
            
            $customers = $this->customerService->getAllCustomers($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $customers->items(),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Customers index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customers/stats
     * ✅ إحصائيات العملاء
     */
    public function stats()
    {
        try {
            $stats = $this->customerService->getCustomersStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Customers stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customers/{id}
     * ✅ عرض بيانات عميل محدد
     */
    public function show($id)
    {
        try {
            $customer = $this->customerService->getCustomerWithDetails($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'العميل غير موجود'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            Log::error('Customer show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/customers
     * ✅ إنشاء عميل جديد
     */
    public function store(Request $request)
    {
        try {
            // ✅ التحقق من المصادقة
            $user = $request->user();
            
            if (!$user) {
                Log::error('User not authenticated');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated. Please login again.'
                ], 401);
            }

            Log::info('Customer creation request received from user: ' . $user->id);
            Log::info('Request data:', $request->all());

            // ✅ التحقق من البيانات
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'phone' => 'required|string|unique:users,phone|min:10|max:15',
                'email' => 'nullable|email|unique:users,email',
                'address' => 'nullable|string|max:500',
                'password' => 'required|string|min:6',
                'identity_image_base64' => 'nullable|string',
                'personal_image_base64' => 'nullable|string',
                'fingerprint_data' => 'nullable|string',
            ]);

            Log::info('Validation passed');

            // ✅ إنشاء العميل
            $result = $this->customerService->createCustomer(
                $validated,
                $user->id
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العميل بنجاح',
                'data' => [
                    'customer' => $result['customer'],
                    'temporary_password' => $result['temporary_password'],
                    'membership_number' => $result['membership_number']
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Customer creation error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/customers/{id}
     * ✅ تحديث بيانات عميل
     */
    public function update(Request $request, $id)
    {
        try {
            $customer = Customer::with('user')->findOrFail($id);

            $validated = $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'phone' => [
                    'sometimes',
                    'string',
                    'regex:/^([0-9\s\-\+\(\)]*)$/',
                    'min:10',
                    'max:15',
                    'unique:users,phone,' . $customer->user_id,
                ],
                'email' => 'nullable|email|unique:users,email,' . $customer->user_id,
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
                $customer->user->update($userData);
            }

            // ✅ تحديث بيانات العميل
            if (isset($validated['address'])) {
                $customer->update(['address' => $validated['address']]);
            }

            Log::info('Customer updated successfully', [
                'customer_id' => $customer->id,
                'user_id' => $customer->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات العميل بنجاح',
                'data' => $customer->fresh('user')
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Customer update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/customers/{id}
     * ✅ حذف عميل
     */
    public function destroy($id)
    {
        try {
            $customer = Customer::with('user')->findOrFail($id);
            
            // ✅ التحقق من وجود العميل
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'العميل غير موجود'
                ], 404);
            }

            $user = $customer->user;
            
            // ✅ حذف العميل والمستخدم
            $customer->delete();
            
            if ($user) {
                $user->delete();
            }

            Log::info('Customer deleted successfully', [
                'customer_id' => $id,
                'user_id' => $customer->user_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف العميل بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Customer destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/customers/{id}/status
     * ✅ تحديث حالة العميل
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:active,pending,suspended,expired'
            ]);

            $customer = Customer::findOrFail($id);
            
            // ✅ تحديث كلا العمودين
            $customer->membership_status = $validated['status'];
            $customer->status = $validated['status'];
            $customer->save();

            Log::info('Customer status updated', [
                'customer_id' => $customer->id,
                'status' => $validated['status']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة العميل بنجاح',
                'data' => [
                    'id' => $customer->id,
                    'status' => $customer->membership_status
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Customer update status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/customers/{id}/renew
     * ✅ تجديد عضوية العميل
     */
    public function renewMembership(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'months' => 'nullable|integer|min:1|max:60'
            ]);

            $months = $validated['months'] ?? 12;
            
            $customer = Customer::findOrFail($id);
            $customer = $this->customerService->renewMembership($customer, $months);

            Log::info('Customer membership renewed', [
                'customer_id' => $customer->id,
                'months' => $months,
                'new_expiry' => $customer->membership_expiry_date
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تجديد عضوية العميل بنجاح',
                'data' => [
                    'id' => $customer->id,
                    'membership_expiry_date' => $customer->membership_expiry_date,
                    'membership_status' => $customer->membership_status
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Customer renew membership error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customers/{id}/transactions
     * ✅ عرض معاملات العميل
     */
    public function transactions($id)
    {
        try {
            $customer = Customer::with(['discountTransactions' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $customer->discountTransactions
            ]);
        } catch (\Exception $e) {
            Log::error('Customer transactions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/customers/{id}/fingerprint
     * ✅ حذف بصمة العميل
     */
    public function deleteFingerprint($id)
    {
        try {
            $customer = Customer::findOrFail($id);
            $customer->fingerprint_data = null;
            $customer->save();

            Log::info('Customer fingerprint deleted', [
                'customer_id' => $customer->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف البصمة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Customer delete fingerprint error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customers/export/excel
     * ✅ تصدير العملاء إلى Excel
     */
    public function exportExcel(Request $request)
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
            ];

            $customers = $this->customerService->getAllCustomers($filters, 1000);
            
            // ✅ يمكن إضافة منطق تصدير Excel هنا
            
            return response()->json([
                'success' => true,
                'message' => 'تم تصدير البيانات بنجاح',
                'data' => $customers->items()
            ]);
        } catch (\Exception $e) {
            Log::error('Customer export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customers/me
     * ✅ بيانات العميل الحالي (للمستخدم العادي)
     */
 
    public function me(Request $request)
{
    try {
        $user = $request->user();
        
        if (!$user || $user->role !== 'customer') {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم ليس عميلاً'
            ], 403);
        }

        $customer = Customer::with(['user', 'discountTransactions'])
            ->where('user_id', $user->id)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات العميل غير موجودة'
            ], 404);
        }

        // إضافة بيانات إضافية
        $customerData = $customer->toArray();
        $customerData['full_name'] = $customer->user->full_name ?? '';
        $customerData['phone'] = $customer->user->phone ?? '';
        $customerData['email'] = $customer->user->email ?? '';
        $customerData['membership_status'] = $customer->membership_status;
        $customerData['days_remaining'] = $customer->days_remaining;
        $customerData['total_savings'] = $customer->total_savings;

        return response()->json([
            'success' => true,
            'data' => $customerData
        ]);
    } catch (\Exception $e) {
        Log::error('Customer me error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function resetPassword($id)
    {
        try {
            // جلب العميل مع المستخدم المرتبط به
            $customer = Customer::with('user')->find($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'العميل غير موجود'
                ], 404);
            }

            if (!$customer->user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم المرتبط بهذا العميل غير موجود'
                ], 404);
            }

            // ✅ تغيير كلمة المرور إلى 123456789
            $newPassword = '123456789';
            $customer->user->password = Hash::make($newPassword);
            $customer->user->save();

            // تسجيل العملية في السجلات
            Log::info('تم إعادة تعيين كلمة مرور العميل', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->user->full_name,
                'admin_id' => auth()->id(),
                'admin_name' => auth()->user()->full_name,
                'new_password' => $newPassword, // تسجيل مؤقت للمساعدة
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
                'new_password' => $newPassword // إرسال كلمة المرور الجديدة للتطبيق
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في إعادة تعيين كلمة مرور العميل: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في إعادة تعيين كلمة المرور'
            ], 500);
        }
    }
}