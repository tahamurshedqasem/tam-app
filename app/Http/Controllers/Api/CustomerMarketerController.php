<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomerMarketerController extends Controller
{
    protected CustomerService $customerService;
    
    protected CommissionService $commissionService;
    

    public function __construct(
        CustomerService $customerService,
        CommissionService $commissionService
    ) {
        $this->customerService = $customerService;
        $this->commissionService = $commissionService;
        // $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/customer-marketers
     * قائمة مسوقي العملاء
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $status = $request->get('status');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $query = User::where('role', 'customer_marketer')
                ->withCount('createdCustomers')
                ->withSum('commissions', 'amount');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            $query->orderBy($sortBy, $sortDirection);

            $marketers = $query->paginate($perPage);

            $marketers->getCollection()->transform(function ($marketer) {
                return [
                    'id' => $marketer->id,
                    'full_name' => $marketer->full_name,
                    'name' => $marketer->full_name,
                    'phone' => $marketer->phone,
                    'email' => $marketer->email,
                    'status' => $marketer->status,
                    'customers_count' => $marketer->created_customers_count ?? 0,
                    'total_commission' => (float) ($marketer->commissions_sum_amount ?? 0),
                    'pending_commission' => (float) ($marketer->pending_commission ?? 0),
                    'paid_commission' => (float) ($marketer->paid_commission ?? 0),
                    'region' => $marketer->region ?? 'غير محدد',
                    'commission_percentage' => 5, // Default percentage
                    'created_at' => $marketer->created_at,
                    'updated_at' => $marketer->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $marketers->items(),
                'meta' => [
                    'current_page' => $marketers->currentPage(),
                    'last_page' => $marketers->lastPage(),
                    'per_page' => $marketers->perPage(),
                    'total' => $marketers->total(),
                    'from' => $marketers->firstItem(),
                    'to' => $marketers->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/customer-marketers
     * إضافة مسوق عملاء جديد
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'phone' => 'required|string|unique:users,phone|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
                'email' => 'nullable|email|unique:users,email',
                'password' => 'required|string|min:6',
                'region' => 'nullable|string|max:255',
                'commission_percentage' => 'nullable|numeric|min:0|max:100',
            ]);

            // Create user
            $user = User::create([
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => 'customer_marketer',
                'status' => 'active',
                'region' => $validated['region'] ?? null,
            ]);

            Log::info('Customer marketer created', [
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'phone' => $user->phone,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة مسوق العملاء بنجاح',
                'id' => $user->id,
                'data' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'status' => $user->status,
                    'region' => $user->region,
                    'commission_percentage' => $validated['commission_percentage'] ?? 5,
                    'created_at' => $user->created_at->toISOString(),
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketers/{id}
     * عرض بيانات مسوق
     */
    public function show($id)
    {
        try {
            $marketer = User::where('role', 'customer_marketer')
                ->withCount('createdCustomers')
                ->withSum('commissions', 'amount')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $marketer->id,
                    'full_name' => $marketer->full_name,
                    'name' => $marketer->full_name,
                    'phone' => $marketer->phone,
                    'email' => $marketer->email,
                    'status' => $marketer->status,
                    'customers_count' => $marketer->created_customers_count ?? 0,
                    'total_commission' => (float) ($marketer->commissions_sum_amount ?? 0),
                    'pending_commission' => (float) ($marketer->pending_commission ?? 0),
                    'paid_commission' => (float) ($marketer->paid_commission ?? 0),
                    'region' => $marketer->region ?? 'غير محدد',
                    'commission_percentage' => 5,
                    'created_at' => $marketer->created_at,
                    'updated_at' => $marketer->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/customer-marketers/{id}
     * تحديث بيانات مسوق
     */
    public function update(Request $request, $id)
    {
        try {
            $marketer = User::where('role', 'customer_marketer')->findOrFail($id);

            $validated = $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'phone' => [
                    'sometimes',
                    'string',
                    'regex:/^([0-9\s\-\+\(\)]*)$/',
                    'min:10',
                    'max:15',
                    Rule::unique('users', 'phone')->ignore($id),
                ],
                'email' => [
                    'nullable',
                    'email',
                    Rule::unique('users', 'email')->ignore($id),
                ],
                'password' => 'nullable|string|min:6',
                'region' => 'nullable|string|max:255',
                'status' => 'sometimes|in:active,inactive,suspended',
                'commission_percentage' => 'nullable|numeric|min:0|max:100',
            ]);

            // Remove commission_percentage from user update as it's not in users table
            unset($validated['commission_percentage']);

            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $marketer->update($validated);

            Log::info('Customer marketer updated', [
                'user_id' => $marketer->id,
                'full_name' => $marketer->full_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات المسوق بنجاح',
                'data' => [
                    'id' => $marketer->id,
                    'full_name' => $marketer->full_name,
                    'phone' => $marketer->phone,
                    'email' => $marketer->email,
                    'status' => $marketer->status,
                    'region' => $marketer->region,
                    'updated_at' => $marketer->updated_at,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/customer-marketers/{id}
     * حذف مسوق
     */
    public function destroy($id)
    {
        try {
            $marketer = User::where('role', 'customer_marketer')->findOrFail($id);

            $customersCount = Customer::where('created_by_marketer', $id)->count();

            if ($customersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "لا يمكن حذف المسوق لأنه لديه {$customersCount} عميل مسجل",
                ], 422);
            }

            $marketer->delete();

            Log::info('Customer marketer deleted', [
                'user_id' => $id,
                'full_name' => $marketer->full_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المسوق بنجاح',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/customer-marketers/{id}/status
     * تحديث حالة المسوق
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,inactive,suspended',
            ]);

            $marketer = User::where('role', 'customer_marketer')->findOrFail($id);
            $marketer->status = $request->status;
            $marketer->save();

            Log::info('Customer marketer status updated', [
                'user_id' => $marketer->id,
                'status' => $marketer->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة المسوق بنجاح',
                'data' => [
                    'id' => $marketer->id,
                    'status' => $marketer->status,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in updateStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketers/stats
     * إحصائيات المسوقين
     */
    public function stats()
    {
        try {
            $stats = [
                'total_marketers' => User::where('role', 'customer_marketer')->count(),
                'active_marketers' => User::where('role', 'customer_marketer')->where('status', 'active')->count(),
                'pending_marketers' => User::where('role', 'customer_marketer')->where('status', 'pending')->count(),
                'suspended_marketers' => User::where('role', 'customer_marketer')->where('status', 'suspended')->count(),
                'total_customers' => Customer::count(),
                'total_commissions' => (float) DB::table('commissions')
                    ->whereIn('user_id', function ($query) {
                        $query->select('id')->from('users')->where('role', 'customer_marketer');
                    })
                    ->where('status', 'paid')
                    ->sum('amount'),
                'pending_commissions' => (float) DB::table('commissions')
                    ->whereIn('user_id', function ($query) {
                        $query->select('id')->from('users')->where('role', 'customer_marketer');
                    })
                    ->where('status', 'pending')
                    ->sum('amount'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketers/customers
     * قائمة عملاء مسوق معين
     */
    public function getCustomers(Request $request)
    {
        try {
            $marketerId = $request->user()->id;

            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');

            $query = Customer::where('created_by_marketer', $marketerId)
                ->with('user');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('membership_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('full_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            }

            $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $customers->items(),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getCustomers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketers/customers/{id}
     * عرض بيانات عميل معين
     */
    public function getCustomer($id)
    {
        try {
            $marketerId = request()->user()->id;

            $customer = Customer::where('created_by_marketer', $marketerId)
                ->with('user')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getCustomer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/customer-marketers/customers/{id}
     * تحديث بيانات عميل
     */
    public function updateCustomer(Request $request, $id)
    {
        try {
            $marketerId = $request->user()->id;

            $customer = Customer::where('created_by_marketer', $marketerId)->findOrFail($id);

            $validated = $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'phone' => [
                    'sometimes',
                    'string',
                    'regex:/^([0-9\s\-\+\(\)]*)$/',
                    'min:10',
                    'max:15',
                    Rule::unique('users', 'phone')->ignore($customer->user_id),
                ],
                'email' => [
                    'nullable',
                    'email',
                    Rule::unique('users', 'email')->ignore($customer->user_id),
                ],
                'address' => 'nullable|string|max:500',
            ]);

            // Update user
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

            // Update customer
            if (isset($validated['address'])) {
                $customer->update(['address' => $validated['address']]);
            }

            Log::info('Customer updated by marketer', [
                'customer_id' => $customer->id,
                'marketer_id' => $marketerId,
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
            Log::error('Error in updateCustomer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/customer-marketers/customers/{id}
     * حذف عميل
     */
    public function deleteCustomer($id)
    {
        try {
            $marketerId = request()->user()->id;

            $customer = Customer::where('created_by_marketer', $marketerId)->findOrFail($id);

            // Delete customer and associated user
            $user = $customer->user;
            $customer->delete();
            $user->delete();

            Log::info('Customer deleted by marketer', [
                'customer_id' => $id,
                'marketer_id' => $marketerId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف العميل بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in deleteCustomer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketers/{id}/commissions
     * عرض عمولات مسوق معين
     */
    public function getMarketerCommissions(Request $request, $id)
    {
        try {
            $marketer = User::where('role', 'customer_marketer')->findOrFail($id);

            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');

            $query = $marketer->commissions();

            if ($status) {
                $query->where('status', $status);
            }

            $commissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $commissions->items(),
                'meta' => [
                    'current_page' => $commissions->currentPage(),
                    'last_page' => $commissions->lastPage(),
                    'per_page' => $commissions->perPage(),
                    'total' => $commissions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getMarketerCommissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketers/me
     * عرض بيانات المسوق الحالي
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->isCustomerMarketer()) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم ليس مسوق عملاء'
                ], 403);
            }

            $marketer = User::where('id', $user->id)
                ->withCount('createdCustomers')
                ->withSum('commissions', 'amount')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $marketer->id,
                    'full_name' => $marketer->full_name,
                    'phone' => $marketer->phone,
                    'email' => $marketer->email,
                    'status' => $marketer->status,
                    'region' => $marketer->region,
                    'customers_count' => $marketer->created_customers_count ?? 0,
                    'total_commission' => (float) ($marketer->commissions_sum_amount ?? 0),
                    'pending_commission' => (float) ($marketer->pending_commission ?? 0),
                    'paid_commission' => (float) ($marketer->paid_commission ?? 0),
                    'created_at' => $marketer->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in me: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketers/dashboard-stats
     * إحصائيات لوحة التحكم للمسوق
     */
    public function dashboardStats(Request $request)
    {
        try {
            $marketerId = $request->user()->id;

            $totalCustomers = Customer::where('created_by_marketer', $marketerId)->count();
            $activeCustomers = Customer::where('created_by_marketer', $marketerId)
                ->where('membership_status', 'active')
                ->count();

            $totalCommissions = Commission::where('user_id', $marketerId)
                ->where('status', 'paid')
                ->sum('amount');

            $pendingCommissions = Commission::where('user_id', $marketerId)
                ->where('status', 'pending')
                ->sum('amount');

            $recentCustomers = Customer::where('created_by_marketer', $marketerId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $recentCommissions = Commission::where('user_id', $marketerId)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_customers' => $totalCustomers,
                    'active_customers' => $activeCustomers,
                    'total_commissions' => (float) $totalCommissions,
                    'pending_commissions' => (float) $pendingCommissions,
                    'recent_customers' => $recentCustomers,
                    'recent_commissions' => $recentCommissions,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in dashboardStats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}