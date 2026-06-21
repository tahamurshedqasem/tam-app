<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institution\CreateInstitutionRequest;
use App\Http\Requests\Institution\UpdateInstitutionRequest;
use App\Http\Resources\InstitutionResource;
use App\Models\Commission;
use App\Models\RevenueTransaction;
use App\Services\InstitutionService;
use App\Models\Institution;
use App\Models\User;
use App\Models\InstitutionType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
class InstitutionController extends Controller
{
    protected InstitutionService $institutionService;
    protected NotificationService $notificationService;


    public function __construct(InstitutionService $institutionService)
    {
        $this->institutionService = $institutionService;
    }

    /**
     * GET /api/institutions
     * قائمة المؤسسات
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'type_id', 'status', 'city']);
            $perPage = $request->get('per_page', 15);
            
            $institutions = $this->institutionService->getAllInstitutions($filters, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => InstitutionResource::collection($institutions),
                'meta' => [
                    'current_page' => $institutions->currentPage(),
                    'last_page' => $institutions->lastPage(),
                    'per_page' => $institutions->perPage(),
                    'total' => $institutions->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Institution index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/institutions
     * إنشاء مؤسسة جديدة (للـ Admin فقط - بدون عمولات)
     * ✅ لا يتم خصم أي ريال من حساب الشركة
     * ✅ لا يتم إضافة أي عمولة للمسوق
     */
     public function myInstitution(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            // ✅ البحث عن المؤسسة التي يملكها المستخدم
            $institution = Institution::where('owner_id', $user->id)
                ->with('type')
                ->first();

            if (!$institution) {
                // ✅ البحث في جدول institution_owners
                $institution = Institution::whereHas('owners', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })->with('type')->first();
            }

            if (!$institution) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد مؤسسة مرتبطة بهذا المالك'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $institution->id,
                    'name' => $institution->name,
                    'type_id' => $institution->type_id,
                    'type' => $institution->type,
                    'phone' => $institution->phone,
                    'email' => $institution->email,
                    'address' => $institution->address,
                    'discount_percentage' => $institution->discount_percentage,
                    'status' => $institution->status,
                    'description' => $institution->description,
                    'agreement_date' => $institution->agreement_date,
                    'agreement_expiry_date' => $institution->agreement_expiry_date,
                    'contract_file' => $institution->contract_file,
                    'created_by_marketer' => $institution->created_by_marketer,
                    'created_at' => $institution->created_at,
                    'updated_at' => $institution->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('My institution error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function store(Request $request): JsonResponse
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
            
            return DB::transaction(function () use ($validated, $request) {
                
                // =============================================
                // 1. إنشاء حساب المالك
                // =============================================
                $owner = User::create([
                    'full_name' => $validated['owner_name'],
                    'phone' => $validated['phone'],
                    'password' => Hash::make($validated['owner_password']),
                    'role' => 'institution_owner',
                    'status' => 'active'
                ]);
                
                // =============================================
                // 2. إنشاء المؤسسة
                // =============================================
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
                    'created_by' => $request->user()->id, // Admin ID
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
                
                // =============================================
                // 3. ربط المالك بالمؤسسة
                // =============================================
                $institution->owners()->attach($owner->id, ['is_primary' => true]);
                
                // =============================================
                // 4. ❌ لا يتم إضافة عمولة ولا خصم (لأن الـ Admin هو من أضاف)
                // =============================================
                Log::info('Institution created by Admin (No commission/deduction)', [
                    'admin_id' => $request->user()->id,
                    'institution_id' => $institution->id,
                    'institution_name' => $institution->name
                ]);
                
                // =============================================
                // 5. إنشاء إشعار للمالك
                // =============================================
                $this->createOwnerNotification($owner, $institution, $validated['owner_password']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'تم إنشاء المؤسسة وحساب المالك بنجاح',
                    'data' => [
                        'institution' => new InstitutionResource($institution->load(['type', 'owner'])),
                        'owner' => [
                            'id' => $owner->id,
                            'full_name' => $owner->full_name,
                            'phone' => $owner->phone,
                        ],
                        'login_instructions' => 'يمكن للمالك تسجيل الدخول باستخدام رقم الهاتف وكلمة المرور'
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
            Log::error('Institution store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
        $this->notificationService->notifyNewInstitution($institution);

    }

    /**
     * حفظ الصورة من Base64
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
     * إنشاء إشعار للمالك الجديد
     */
    protected function createOwnerNotification(User $owner, Institution $institution, string $password): void
    {
        try {
            \App\Models\Notification::create([
                'user_id' => $owner->id,
                'title' => '✅ تم إنشاء حسابك كمالك مؤسسة',
                'body' => "مرحباً {$owner->full_name}، تم إنشاء حسابك للمؤسسة {$institution->name}.\n\n"
                    . "📱 رقم الهاتف: {$owner->phone}\n"
                    . "🔑 كلمة المرور: {$password}\n\n"
                    . "يمكنك تسجيل الدخول باستخدام هذه البيانات.",
                'type' => 'success',
                'data' => json_encode([
                    'institution_id' => $institution->id,
                    'institution_name' => $institution->name,
                    'phone' => $owner->phone,
                ])
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create owner notification: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/institutions/{institution}
     * عرض بيانات مؤسسة
     */
    public function show(Institution $institution): JsonResponse
    {
        try {
            $institution->load(['type', 'owner', 'createdBy', 'owners']);
            
            return response()->json([
                'success' => true,
                'data' => new InstitutionResource($institution)
            ]);
        } catch (\Exception $e) {
            Log::error('Institution show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/institutions/{institution}
     * تحديث بيانات مؤسسة
     */
    public function update(UpdateInstitutionRequest $request, Institution $institution): JsonResponse
    {
        try {
            $data = $request->validated();
            
            if (isset($data['contract_base64']) && !empty($data['contract_base64'])) {
                if ($institution->contract_file) {
                    \Storage::disk('public')->delete($institution->contract_file);
                }
                $contractPath = $this->saveBase64Image($data['contract_base64'], 'contracts');
                $data['contract_file'] = $contractPath;
                unset($data['contract_base64']);
            }
            
            $updatedInstitution = $this->institutionService->updateInstitution($institution, $data);
            
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات المؤسسة بنجاح',
                'data' => new InstitutionResource($updatedInstitution)
            ]);
        } catch (\Exception $e) {
            Log::error('Institution update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/institutions/{institution}
     * حذف مؤسسة
     */
    public function destroy(Institution $institution): JsonResponse
    {
        try {
            return DB::transaction(function () use ($institution) {
                if ($institution->contract_file) {
                    \Storage::disk('public')->delete($institution->contract_file);
                }
                
                $institution->owners()->detach();
                $this->institutionService->deleteInstitution($institution);
                
                return response()->json([
                    'success' => true,
                    'message' => 'تم حذف المؤسسة بنجاح'
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Institution destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institutions/types
     * أنواع المؤسسات
     */
    public function getTypes(): JsonResponse
    {
        try {
            $types = InstitutionType::where('is_active', true)->get();
            
            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            Log::error('Get institution types error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institutions/nearby
     * المؤسسات القريبة
     */
    public function nearby(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'distance' => 'nullable|numeric|min:1|max:50'
            ]);
            
            $latitude = $request->get('latitude');
            $longitude = $request->get('longitude');
            $distance = $request->get('distance', 10);
            
            $institutions = $this->institutionService->getNearbyInstitutions($latitude, $longitude, $distance);
            
            return response()->json([
                'success' => true,
                'data' => InstitutionResource::collection($institutions)
            ]);
        } catch (\Exception $e) {
            Log::error('Nearby institutions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institutions/stats
     * إحصائيات المؤسسات
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Institution::count(),
                'active' => Institution::where('status', 'active')->count(),
                'pending' => Institution::where('status', 'pending')->count(),
                'suspended' => Institution::where('status', 'suspended')->count(),
                'expired' => Institution::where('status', 'expired')->count(),
                'average_discount' => round(Institution::avg('discount_percentage') ?? 0, 1),
                'total_discounts_given' => \App\Models\DiscountTransaction::count(),
                'total_savings_given' => (float) \App\Models\DiscountTransaction::sum('amount_saved'),
                'total_commissions' => (float) Commission::where('role', 'institution_marketer')
                    ->where('status', 'paid')
                    ->sum('amount'),
                'pending_commissions' => (float) Commission::where('role', 'institution_marketer')
                    ->where('status', 'pending')
                    ->sum('amount'),
                'total_company_deductions' => (float) RevenueTransaction::where('type', 'commission_payment')
                    ->where('net_amount', '<', 0)
                    ->sum('net_amount') * -1,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Institution stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/institutions/{institution}/renew-agreement
     * تجديد اتفاقية المؤسسة
     */
    public function renewAgreement(Request $request, Institution $institution): JsonResponse
    {
        try {
            $request->validate([
                'months' => 'nullable|integer|min:1|max:60'
            ]);
            
            $months = $request->get('months', 12);
            $institution = $this->institutionService->renewAgreement($institution, $months);
            
            // ✅ تجديد بدون عمولة (لأن الـ Admin هو من يجدد)
            
            return response()->json([
                'success' => true,
                'message' => 'تم تجديد اتفاقية المؤسسة بنجاح',
                'data' => [
                    'agreement_expiry_date' => $institution->agreement_expiry_date,
                    'status' => $institution->status
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Renew agreement error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/institutions/{institution}/update-discount
     * تحديث نسبة الخصم
     */
    public function updateDiscount(Request $request, Institution $institution): JsonResponse
    {
        try {
            $request->validate([
                'discount_percentage' => 'required|numeric|min:0|max:100'
            ]);
            
            $institution = $this->institutionService->updateDiscountPercentage(
                $institution,
                (float) $request->discount_percentage
            );
            
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث نسبة الخصم بنجاح',
                'data' => [
                    'discount_percentage' => $institution->discount_percentage
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Update discount error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institution-owner/my-institution
     * الحصول على مؤسسة المالك الحالي
     */
    // public function myInstitution(Request $request): JsonResponse
    // {
    //     try {
    //         $user = $request->user();
            
    //         $institution = Institution::where('owner_id', $user->id)
    //             ->with('type')
    //             ->first();
            
    //         if (!$institution) {
    //             $institution = Institution::whereHas('owners', function($query) use ($user) {
    //                 $query->where('user_id', $user->id);
    //             })->with('type')->first();
    //         }
            
    //         if (!$institution) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'لا توجد مؤسسة مرتبطة بهذا المالك'
    //             ], 404);
    //         }
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => new InstitutionResource($institution)
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('My institution error: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * POST /api/institutions/{institution}/add-owner
     * إضافة مالك للمؤسسة
     */
    public function addOwner(Request $request, Institution $institution): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'is_primary' => 'nullable|boolean'
            ]);
            
            $user = User::findOrFail($request->user_id);
            $institution = $this->institutionService->addOwner(
                $institution, 
                $user, 
                $request->is_primary ?? false
            );
            
            return response()->json([
                'success' => true,
                'message' => 'تم إضافة المالك بنجاح',
                'data' => new InstitutionResource($institution)
            ]);
        } catch (\Exception $e) {
            Log::error('Add owner error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/institutions/{institution}/remove-owner/{user}
     * إزالة مالك من المؤسسة
     */
    public function removeOwner(Institution $institution, User $user): JsonResponse
    {
        try {
            $institution = $this->institutionService->removeOwner($institution, $user);
            
            return response()->json([
                'success' => true,
                'message' => 'تم إزالة المالك بنجاح',
                'data' => new InstitutionResource($institution)
            ]);
        } catch (\Exception $e) {
            Log::error('Remove owner error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}