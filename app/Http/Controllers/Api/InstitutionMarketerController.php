<?php

namespace App\Http\Controllers\Api;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Institution;
use App\Models\Commission;
use App\Models\InstitutionType;
use App\Models\RevenueTransaction;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class InstitutionMarketerController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        // $this->middleware('auth:sanctum');
    }

    // ==================== MARKETER MANAGEMENT ====================

    /**
     * GET /api/admin/institution-marketers
     * ✅ قائمة مسوقي المؤسسات (للمدير فقط)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $status = $request->get('status');
            $performance = $request->get('performance');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $query = User::where('role', 'institution_marketer')
                ->withCount('createdInstitutions')
                ->withSum('commissions', 'amount');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($performance) {
                // يمكن إضافة منطق للفلترة حسب الأداء
            }

            $query->orderBy($sortBy, $sortDirection);

            $marketers = $query->paginate($perPage);

            $marketers->getCollection()->transform(function ($marketer) {
                $commission = (float) ($marketer->commissions_sum_amount ?? 0);
                
                return [
                    'id' => $marketer->id,
                    'full_name' => $marketer->full_name,
                    'name' => $marketer->full_name,
                    'phone' => $marketer->phone,
                    'email' => $marketer->email,
                    'status' => $marketer->status,
                    'institutions_count' => $marketer->created_institutions_count ?? 0,
                    'total_commission' => $commission,
                    'pending_commission' => (float) $marketer->pending_commission ?? 0,
                    'paid_commission' => (float) $marketer->paid_commission ?? 0,
                    'region' => $marketer->region ?? 'غير محدد',
                    'commission_rate' => $marketer->commission_rate ?? 5,
                    'performance' => $this->getPerformanceFromCommission($commission),
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
     * POST /api/admin/institution-marketers
     * ✅ إضافة مسوق مؤسسات جديد (للمدير فقط)
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
                'commission_rate' => 'nullable|numeric|min:0|max:100',
            ]);

            return DB::transaction(function () use ($validated) {
                // Create user
                $user = User::create([
                    'full_name' => $validated['full_name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'role' => 'institution_marketer',
                    'status' => 'active',
                    'region' => $validated['region'] ?? null,
                    'commission_rate' => $validated['commission_rate'] ?? 5,
                ]);

                Log::info('Institution marketer created', [
                    'user_id' => $user->id,
                    'full_name' => $user->full_name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم إضافة مسوق المؤسسات بنجاح',
                    'data' => [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'status' => $user->status,
                        'region' => $user->region,
                        'commission_rate' => $user->commission_rate,
                        'created_at' => $user->created_at,
                    ]
                ], 201);
            });
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
     * GET /api/admin/institution-marketers/{id}
     * ✅ عرض بيانات مسوق (للمدير فقط)
     */
    public function show($id)
    {
        try {
            $marketer = User::where('role', 'institution_marketer')
                ->withCount('createdInstitutions')
                ->withSum('commissions', 'amount')
                ->findOrFail($id);

            $commission = (float) ($marketer->commissions_sum_amount ?? 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $marketer->id,
                    'full_name' => $marketer->full_name,
                    'name' => $marketer->full_name,
                    'phone' => $marketer->phone,
                    'email' => $marketer->email,
                    'status' => $marketer->status,
                    'institutions_count' => $marketer->created_institutions_count ?? 0,
                    'total_commission' => $commission,
                    'pending_commission' => (float) $marketer->pending_commission ?? 0,
                    'paid_commission' => (float) $marketer->paid_commission ?? 0,
                    'region' => $marketer->region ?? 'غير محدد',
                    'commission_rate' => $marketer->commission_rate ?? 5,
                    'performance' => $this->getPerformanceFromCommission($commission),
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
     * PUT /api/admin/institution-marketers/{id}
     * ✅ تحديث بيانات مسوق (للمدير فقط)
     */
    public function update(Request $request, $id)
    {
        try {
            $marketer = User::where('role', 'institution_marketer')->findOrFail($id);

            $validated = $request->validate([
                'full_name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|unique:users,phone,' . $id . '|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:15',
                'email' => 'nullable|email|unique:users,email,' . $id,
                'password' => 'nullable|string|min:6',
                'region' => 'nullable|string|max:255',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'status' => 'sometimes|in:active,inactive,suspended',
            ]);

            return DB::transaction(function () use ($marketer, $validated) {
                if (isset($validated['password'])) {
                    $validated['password'] = Hash::make($validated['password']);
                }

                // Remove commission_rate from user update if not in users table
                // Or add it to users table migration
                $marketer->update($validated);

                Log::info('Institution marketer updated', [
                    'user_id' => $marketer->id,
                    'full_name' => $marketer->full_name
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
                        'commission_rate' => $marketer->commission_rate ?? 5,
                        'updated_at' => $marketer->updated_at,
                    ]
                ]);
            });
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
     * DELETE /api/admin/institution-marketers/{id}
     * ✅ حذف مسوق (للمدير فقط)
     */
    public function destroy($id)
    {
        try {
            $marketer = User::where('role', 'institution_marketer')->findOrFail($id);

            // Check if marketer has institutions
            $institutionsCount = Institution::where('created_by_marketer', $id)->count();
            
            if ($institutionsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "لا يمكن حذف المسوق لأنه لديه {$institutionsCount} مؤسسة مسجلة",
                ], 422);
            }

            $marketer->delete();

            Log::info('Institution marketer deleted', [
                'user_id' => $id,
                'full_name' => $marketer->full_name
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
     * PUT /api/admin/institution-marketers/{id}/status
     * ✅ تحديث حالة المسوق (للمدير فقط)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:active,inactive,suspended',
            ]);

            $marketer = User::where('role', 'institution_marketer')->findOrFail($id);
            $marketer->status = $request->status;
            $marketer->save();

            Log::info('Institution marketer status updated', [
                'user_id' => $marketer->id,
                'status' => $marketer->status
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
     * GET /api/admin/institution-marketers/stats
     * ✅ إحصائيات المسوقين (للمدير فقط)
     */
    public function stats()
    {
        try {
            $stats = [
                'total_marketers' => User::where('role', 'institution_marketer')->count(),
                'active_marketers' => User::where('role', 'institution_marketer')->where('status', 'active')->count(),
                'pending_marketers' => User::where('role', 'institution_marketer')->where('status', 'pending')->count(),
                'suspended_marketers' => User::where('role', 'institution_marketer')->where('status', 'suspended')->count(),
                'total_institutions' => Institution::count(),
                'total_commissions' => (float) Commission::whereIn('user_id', function($query) {
                    $query->select('id')->from('users')->where('role', 'institution_marketer');
                })->sum('amount'),
                'pending_commissions' => (float) Commission::whereIn('user_id', function($query) {
                    $query->select('id')->from('users')->where('role', 'institution_marketer');
                })->where('status', 'pending')->sum('amount'),
                'paid_commissions' => (float) Commission::whereIn('user_id', function($query) {
                    $query->select('id')->from('users')->where('role', 'institution_marketer');
                })->where('status', 'paid')->sum('amount'),
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

    // ==================== INSTITUTION MANAGEMENT FOR MARKETERS ====================

    /**
     * GET /api/institution-marketers/institutions
     * ✅ قائمة المؤسسات التي أضافها المسوق
     */
    public function getInstitutions(Request $request)
    {
        try {
            $marketerId = $request->user()->id;
            
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            
            $query = Institution::where('created_by_marketer', $marketerId)
                ->with('type', 'owner');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%");
                });
            }

            $institutions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $institutions->items(),
                'meta' => [
                    'current_page' => $institutions->currentPage(),
                    'last_page' => $institutions->lastPage(),
                    'per_page' => $institutions->perPage(),
                    'total' => $institutions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getInstitutions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/institution-marketers/institutions
     * ✅ إضافة مؤسسة جديدة بواسطة مسوق المؤسسات
     */
    public function storeInstitution(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type_id' => 'required|exists:institution_types,id',
                'phone' => 'required|string|unique:institutions,phone',
                'email' => 'nullable|email',
                'address' => 'required|string',
                'discount_percentage' => 'required|numeric|min:0|max:100',
                'agreement_date' => 'required|date',
                'agreement_expiry_date' => 'nullable|date|after:agreement_date',
                'owner_name' => 'required|string|max:255',
                'owner_password' => 'required|string|min:6',
                'contract_base64' => 'nullable|string',
                'description' => 'nullable|string',
                'business_hours' => 'nullable|json',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
            ]);

            $marketer = $request->user();

            return DB::transaction(function () use ($validated, $marketer) {
                
                // 1. إنشاء حساب المالك
                $owner = User::create([
                    'full_name' => $validated['owner_name'],
                    'phone' => $validated['phone'],
                    'password' => Hash::make($validated['owner_password']),
                    'role' => 'institution_owner',
                    'status' => 'active'
                ]);
                
                // 2. إنشاء المؤسسة
                $institutionData = [
                    'name' => $validated['name'],
                    'type_id' => $validated['type_id'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'],
                    'discount_percentage' => $validated['discount_percentage'],
                    'agreement_date' => $validated['agreement_date'],
                    'agreement_expiry_date' => $validated['agreement_expiry_date'] ?? null,
                    'owner_id' => $owner->id,
                    'created_by_marketer' => $marketer->id,
                    'status' => 'active',
                    'description' => $validated['description'] ?? null,
                    'business_hours' => $validated['business_hours'] ?? null,
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                ];
                
                // معالجة صورة العقد
                if (isset($validated['contract_base64']) && !empty($validated['contract_base64'])) {
                    $contractPath = $this->saveBase64Image($validated['contract_base64'], 'contracts');
                    $institutionData['contract_file'] = $contractPath;
                }
                
                $institution = Institution::create($institutionData);
                
                // 3. ربط المالك بالمؤسسة
                $institution->owners()->attach($owner->id, ['is_primary' => true]);
                
                // 4. إنشاء العمولة (400 ريال)
                $this->createMarketerCommission($institution, $marketer);
                
                // 5. إشعارات
                $this->createOwnerNotification($owner, $institution, $validated['owner_password']);
                $this->createMarketerNotification($institution, $marketer);
                
                // 6. إشعار لجميع العملاء
                $this->notificationService->notifyNewInstitution($institution);

                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء المؤسسة بنجاح',
                    'data' => [
                        'institution' => $institution->load(['type', 'owner']),
                        'owner' => [
                            'id' => $owner->id,
                            'full_name' => $owner->full_name,
                            'phone' => $owner->phone,
                        ],
                        'commission' => [
                            'amount' => 400,
                            'currency' => 'YER',
                            'status' => 'pending'
                        ]
                    ]
                ], 201);
            });
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in storeInstitution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institution-marketers/institutions/{id}
     * ✅ عرض مؤسسة محددة
     */
    public function getInstitution($id)
    {
        try {
            $marketerId = request()->user()->id;
            
            $institution = Institution::where('created_by_marketer', $marketerId)
                ->with('type', 'owner')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $institution
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getInstitution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/institution-marketers/institutions/{id}
     * ✅ تحديث مؤسسة
     */
    public function updateInstitution(Request $request, $id)
    {
        try {
            $marketerId = $request->user()->id;
            
            $institution = Institution::where('created_by_marketer', $marketerId)->findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|unique:institutions,phone,' . $id,
                'email' => 'nullable|email',
                'address' => 'sometimes|string|max:500',
                'discount_percentage' => 'sometimes|numeric|min:0|max:100',
                'description' => 'nullable|string',
                'business_hours' => 'nullable|json',
                'status' => 'sometimes|in:active,suspended',
            ]);

            $institution->update($validated);

            Log::info('Institution updated by marketer', [
                'institution_id' => $institution->id,
                'marketer_id' => $marketerId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث المؤسسة بنجاح',
                'data' => $institution->fresh('type')
            ]);
        } catch (\Exception $e) {
            Log::error('Error in updateInstitution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/institution-marketers/institutions/{id}
     * ✅ حذف مؤسسة
     */
    public function deleteInstitution($id)
    {
        try {
            $marketerId = request()->user()->id;
            
            $institution = Institution::where('created_by_marketer', $marketerId)->findOrFail($id);
            
            // حذف المؤسسة
            $institution->delete();

            Log::info('Institution deleted by marketer', [
                'institution_id' => $id,
                'marketer_id' => $marketerId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المؤسسة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in deleteInstitution: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== COMMISSION METHODS ====================

    /**
     * GET /api/institution-marketers/{id}/commissions
     * ✅ عرض عمولات مسوق معين
     */
    public function getMarketerCommissions(Request $request, $id)
    {
        try {
            $marketer = User::where('role', 'institution_marketer')->findOrFail($id);

            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');

            $query = Commission::where('user_id', $marketer->id);

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
     * GET /api/institution-marketers/me
     * ✅ عرض بيانات المسوق الحالي
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->isInstitutionMarketer()) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم ليس مسوق مؤسسات'
                ], 403);
            }

            $marketer = User::where('id', $user->id)
                ->withCount('createdInstitutions')
                ->withSum('commissions', 'amount')
                ->first();

            $commission = (float) ($marketer->commissions_sum_amount ?? 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $marketer->id,
                    'full_name' => $marketer->full_name,
                    'phone' => $marketer->phone,
                    'email' => $marketer->email,
                    'status' => $marketer->status,
                    'region' => $marketer->region,
                    'institutions_count' => $marketer->created_institutions_count ?? 0,
                    'total_commission' => $commission,
                    'pending_commission' => (float) $marketer->pending_commission ?? 0,
                    'paid_commission' => (float) $marketer->paid_commission ?? 0,
                    'commission_rate' => $marketer->commission_rate ?? 5,
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
     * GET /api/institution-marketers/dashboard-stats
     * ✅ إحصائيات لوحة التحكم للمسوق
     */
    public function dashboardStats(Request $request)
    {
        try {
            $marketerId = $request->user()->id;

            $totalInstitutions = Institution::where('created_by_marketer', $marketerId)->count();
            $activeInstitutions = Institution::where('created_by_marketer', $marketerId)
                ->where('status', 'active')
                ->count();

            $totalCommissions = Commission::where('user_id', $marketerId)
                ->where('status', 'paid')
                ->sum('amount');

            $pendingCommissions = Commission::where('user_id', $marketerId)
                ->where('status', 'pending')
                ->sum('amount');

            $recentInstitutions = Institution::where('created_by_marketer', $marketerId)
                ->with('type')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $recentCommissions = Commission::where('user_id', $marketerId)
                ->with('institution')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_institutions' => $totalInstitutions,
                    'active_institutions' => $activeInstitutions,
                    'total_commissions' => (float) $totalCommissions,
                    'pending_commissions' => (float) $pendingCommissions,
                    'recent_institutions' => $recentInstitutions,
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

    // ==================== HELPER METHODS ====================

    /**
     * ✅ إنشاء عمولة للمسوق
     */
    protected function createMarketerCommission(Institution $institution, User $marketer): void
    {
        try {
            $commissionAmount = 400; // 400 YER
            $serviceFee = 3000; // 3000 YER

            Log::info('🔄 Creating commission for institution marketer', [
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'commission_amount' => $commissionAmount,
            ]);

            // 1️⃣ إنشاء العمولة
            $commission = Commission::create([
                'user_id' => $marketer->id,
                'role' => 'institution_marketer',
                'amount' => $commissionAmount,
                'commission_percentage' => 0,
                'reason' => "عمولة عن تسجيل مؤسسة جديدة: {$institution->name}",
                'institution_id' => $institution->id,
                'transaction_id' => null,
                'status' => 'pending',
                'currency' => 'YER',
                'service_fee' => $serviceFee, // ✅ 3000 بدلاً من 0
                'customer_discount' => 0,
                'due_date' => now()->addDays(30),
                'notes' => "عمولة تسجيل مؤسسة جديدة - {$institution->name}"
            ]);

            Log::info('✅ Commission created', [
                'commission_id' => $commission->id,
            ]);

            // 2️⃣ تحديث إحصائيات المسوق
            $marketer->increment('institutions_count');
            $marketer->increment('pending_commission', $commissionAmount);
            $marketer->increment('total_commission', $commissionAmount);

            Log::info('✅ Marketer stats updated', [
                'marketer_id' => $marketer->id,
                'institutions_count' => $marketer->institutions_count,
                'pending_commission' => $marketer->pending_commission,
                'total_commission' => $marketer->total_commission,
            ]);

            // 3️⃣ ✅ الحصول على آخر total من revenue_transactions
            $lastRecord = RevenueTransaction::orderBy('id', 'desc')->first();
            $previousTotal = $lastRecord ? (float) $lastRecord->total : 0;
            $newTotal = max(0, $previousTotal - $commissionAmount);

            Log::info('📊 Total calculation', [
                'previous_total' => $previousTotal,
                'deduction_amount' => $commissionAmount,
                'new_total' => $newTotal,
            ]);

            // 4️⃣ ✅ إنشاء معاملة الإيرادات مع الخصم
            $revenueTransaction = RevenueTransaction::create([
                'type' => 'institution_registration',
                'gross_amount' => $serviceFee,
                'total_commissions' => $commissionAmount,
                'net_amount' => 0.00,
                'total' => $newTotal, // ✅ الخصم هنا
                'commission_breakdown' => [
                    'commission_id' => $commission->id,
                    'institution_name' => $institution->name,
                    'institution_id' => $institution->id,
                    'marketer_name' => $marketer->full_name,
                    'marketer_id' => $marketer->id,
                    'commission_amount' => $commissionAmount,
                    'service_fee' => $serviceFee,
                    'previous_total' => $previousTotal,
                    'new_total' => $newTotal,
                ],
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'status' => 'completed',
                'currency' => 'YER',
                'transaction_date' => now(),
                'notes' => "تسجيل مؤسسة جديدة: {$institution->name} - خصم {$commissionAmount} YER من total",
            ]);

            Log::info('✅ Revenue transaction created with deduction', [
                'transaction_id' => $revenueTransaction->id,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
                'deducted_amount' => $commissionAmount,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to create commission and revenue: ' . $e->getMessage(), [
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'trace' => $e->getTraceAsString()
            ]);
            // لا نريد أن يفشل إنشاء المؤسسة بسبب فشل إنشاء العمولة
        }
    }

    /**
     * ✅ حفظ الصورة من Base64
     */
    protected function saveBase64Image(?string $base64Image, string $directory): ?string
    {
        if (empty($base64Image)) {
            return null;
        }

        try {
            $base64Image = trim($base64Image);
            
            if (str_contains($base64Image, 'base64,')) {
                $base64Image = explode('base64,', $base64Image)[1];
            }
            
            $imageData = base64_decode($base64Image);
            
            if ($imageData === false) {
                return null;
            }
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $imageData);
            finfo_close($finfo);
            
            $extension = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                default => 'png'
            };
            
            $fileName = \Illuminate\Support\Str::uuid() . '_' . time() . '.' . $extension;
            $filePath = $directory . '/' . $fileName;
            
            \Storage::disk('public')->put($filePath, $imageData);
            
            return $filePath;
            
        } catch (\Exception $e) {
            Log::error('Save base64 image error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ إنشاء إشعار للمالك الجديد
     */
    protected function createOwnerNotification(User $owner, Institution $institution, string $password): void
    {
        try {
            $this->notificationService->createNotification(
                $owner->id,
                '✅ تم إنشاء حسابك كمالك مؤسسة',
                "مرحباً {$owner->full_name}، تم إنشاء حسابك للمؤسسة {$institution->name}.\n\n"
                . "📱 رقم الهاتف: {$owner->phone}\n"
                . "🔑 كلمة المرور: {$password}\n\n"
                . "يمكنك تسجيل الدخول باستخدام هذه البيانات.",
                'success',
                [
                    'institution_id' => $institution->id,
                    'institution_name' => $institution->name,
                    'phone' => $owner->phone,
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create owner notification: ' . $e->getMessage());
        }
    }

    /**
     * ✅ إنشاء إشعار للمسوق
     */
    protected function createMarketerNotification(Institution $institution, User $marketer): void
    {
        try {
            $this->notificationService->createNotification(
                $marketer->id,
                '🏢 تم تسجيل مؤسسة جديدة',
                "تم تسجيل مؤسسة {$institution->name} بنجاح. تم إضافة 400 ريال إلى عمولاتك.",
                'success',
                [
                    'institution_id' => $institution->id,
                    'institution_name' => $institution->name,
                    'commission' => 400,
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create marketer notification: ' . $e->getMessage());
        }
    }

    /**
     * ✅ حساب الأداء بناءً على العمولة
     */
    private function getPerformanceFromCommission(float $commission): string
    {
        if ($commission >= 40000) return 'ممتاز';
        if ($commission >= 20000) return 'جيد';
        if ($commission >= 10000) return 'متوسط';
        return 'ضعيف';
    }
    // في InstitutionMarketerController.php

public function storeInstitutionType(Request $request): JsonResponse
{
    try {
        $user = $request->user();
        
        // ✅ التحقق من أن المستخدم مسوق أو مدير
        if (!in_array($user->role, ['admin', 'institution_marketer'])) {
            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:institution_types,name',
            'name_ar' => 'required|string|max:255|unique:institution_types,name_ar',
        ]);

        $type = InstitutionType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء نوع المؤسسة بنجاح',
            'data' => $type
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'خطأ في التحقق من البيانات',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        Log::error('❌ Error creating institution type: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء إنشاء نوع المؤسسة: ' . $e->getMessage()
        ], 500);
    }
}
}