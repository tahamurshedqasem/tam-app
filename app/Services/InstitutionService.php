<?php

namespace App\Services;

use App\Contracts\Repositories\InstitutionRepositoryInterface;
use App\Models\Institution;
use App\Models\User;
use App\Models\Commission;
use App\Models\RevenueTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InstitutionService
{
    protected InstitutionRepositoryInterface $institutionRepository;
    protected CommissionService $commissionService;

    // Fixed values
    protected float $serviceFee = 3000; // 3000 YER
    protected float $institutionMarketerCommission = 400; // 400 YER
    protected string $currency = 'YER';

    public function __construct(
        InstitutionRepositoryInterface $institutionRepository,
        CommissionService $commissionService
    ) {
        $this->institutionRepository = $institutionRepository;
        $this->commissionService = $commissionService;
    }

    public function getAllInstitutions(array $filters = [], int $perPage = 15)
    {
        return $this->institutionRepository->all($filters, $perPage);
    }

    public function getNearbyInstitutions(float $latitude, float $longitude, float $distance = 10)
    {
        return $this->institutionRepository->getNearby($latitude, $longitude, $distance);
    }

    /**
     * إنشاء مؤسسة جديدة
     */
    public function createInstitution(array $data, int $marketerId): Institution
    {
        return DB::transaction(function () use ($data, $marketerId) {
            Log::info('🚀 Creating institution...');

            // إنشاء المؤسسة
            $data['created_by_marketer'] = $marketerId;
            $data['status'] = 'active';
            $institution = $this->institutionRepository->create($data);

            $marketer = User::find($marketerId);
            
            if ($marketer && $marketer->isInstitutionMarketer()) {
                $commissionAmount = 400;
                $serviceFee = 3000;

                // 1️⃣ إنشاء العمولة
                $commission = Commission::create([
                    'user_id' => $marketer->id,
                    'role' => 'institution_marketer',
                    'amount' => $commissionAmount,
                    'commission_percentage' => 0,
                    'reason' => "عمولة عن تسجيل مؤسسة جديدة: {$institution->name}",
                    'institution_id' => $institution->id,
                    'status' => 'pending',
                    'currency' => 'YER',
                    'service_fee' => $serviceFee,
                    'customer_discount' => 0,
                    'due_date' => now()->addDays(30),
                ]);

                // 2️⃣ تحديث المسوق
                $marketer->increment('institutions_count');
                $marketer->increment('pending_commission', $commissionAmount);
                $marketer->increment('total_commission', $commissionAmount);

                // 3️⃣ الحصول على آخر total
                $lastRecord = RevenueTransaction::orderBy('id', 'desc')->first();
                $previousTotal = $lastRecord ? (float) $lastRecord->total : 0;
                $newTotal = max(0, $previousTotal - $commissionAmount);

                // 4️⃣ إنشاء معاملة الإيرادات مع الخصم
                RevenueTransaction::create([
                    'type' => 'institution_registration',
                    'gross_amount' => $serviceFee,
                    'total_commissions' => $commissionAmount,
                    'net_amount' => $serviceFee - $commissionAmount,
                    'total' => $newTotal,
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

                Log::info('✅ Commission and revenue created with deduction', [
                    'commission_id' => $commission->id,
                    'previous_total' => $previousTotal,
                    'new_total' => $newTotal,
                    'deducted' => $commissionAmount,
                ]);
            }

            return $institution;
        });
    }

    /**
     * تحديث مؤسسة
     */
    public function updateInstitution(int $id, array $data): Institution
    {
        Log::info('🔄 Updating institution', [
            'institution_id' => $id,
            'data' => $data
        ]);

        $institution = $this->institutionRepository->update($id, $data);

        Log::info('✅ Institution updated', [
            'institution_id' => $institution->id,
            'name' => $institution->name
        ]);

        return $institution;
    }

    /**
     * حذف مؤسسة
     */
  public function deleteInstitution($institution): bool
{
    // ✅ إذا كان $institution هو Model، استخرج الـ ID
    if ($institution instanceof Institution) {
        $id = $institution->id;
        $institutionModel = $institution;
    } else {
        $id = $institution;
        $institutionModel = $this->institutionRepository->find($id);
    }
    
    Log::info('🗑️ Deleting institution', ['institution_id' => $id]);

    // ✅ التحقق من وجود عمليات مرتبطة باستخدام العلاقة الصحيحة
    if ($institutionModel) {
        // استخدام discountTransactions بدلاً من transactions
        if ($institutionModel->discountTransactions()->exists()) {
            Log::warning('⚠️ Cannot delete institution with discount transactions', [
                'institution_id' => $id,
                'transactions_count' => $institutionModel->discountTransactions()->count()
            ]);
            throw new \Exception('لا يمكن حذف المؤسسة لأنها مرتبطة بمعاملات خصم');
        }
    }

    // حذف العمولات المرتبطة
    Commission::where('institution_id', $id)->delete();

    $deleted = $this->institutionRepository->delete($id);

    Log::info('✅ Institution deleted', ['institution_id' => $id]);

    return $deleted;
}
    /**
     * تغيير حالة المؤسسة
     */
    public function updateInstitutionStatus(int $id, string $status): Institution
    {
        Log::info('🔄 Updating institution status', [
            'institution_id' => $id,
            'status' => $status
        ]);

        $institution = $this->institutionRepository->update($id, ['status' => $status]);

        Log::info('✅ Institution status updated', [
            'institution_id' => $institution->id,
            'new_status' => $institution->status
        ]);

        return $institution;
    }

    /**
     * تجديد اتفاقية المؤسسة
     */
    public function renewAgreement(int $id, int $months = 12): Institution
    {
        Log::info('🔄 Renewing institution agreement', [
            'institution_id' => $id,
            'months' => $months
        ]);

        $institution = $this->institutionRepository->find($id);

        $currentExpiry = $institution->agreement_expiry_date ?? now();
        $newExpiry = $currentExpiry->addMonths($months);

        $institution = $this->institutionRepository->update($id, [
            'agreement_expiry_date' => $newExpiry,
            'agreement_date' => now(),
            'status' => 'active'
        ]);

        Log::info('✅ Agreement renewed', [
            'institution_id' => $institution->id,
            'new_expiry_date' => $newExpiry,
            'months_added' => $months
        ]);

        return $institution;
    }

    /**
     * الحصول على تفاصيل المؤسسة
     */
    public function getInstitutionDetails(int $id): ?Institution
    {
        Log::info('📊 Getting institution details', ['institution_id' => $id]);

        $institution = $this->institutionRepository->find($id);

        if ($institution) {
            // تحميل العلاقات
            $institution->load(['type', 'marketer', 'transactions']);
            
            // إحصائيات إضافية
            $institution->statistics = [
                'total_transactions' => $institution->transactions()->count(),
                'total_revenue' => $institution->transactions()->sum('amount'),
                'total_commissions' => Commission::where('institution_id', $id)->sum('amount'),
            ];
        }

        return $institution;
    }

    /**
     * الحصول على إحصائيات المؤسسات
     */
    public function getInstitutionsStatistics(): array
    {
        Log::info('📊 Getting institutions statistics');

        $stats = [
            'total' => Institution::count(),
            'active' => Institution::where('status', 'active')->count(),
            'pending' => Institution::where('status', 'pending')->count(),
            'suspended' => Institution::where('status', 'suspended')->count(),
            'average_discount' => Institution::avg('discount_percentage') ?? 0,
            'total_revenue' => RevenueTransaction::whereHas('institution')->sum('net_amount'),
            'total_commissions' => Commission::where('role', 'institution_marketer')->sum('amount'),
        ];

        Log::info('✅ Statistics retrieved', $stats);

        return $stats;
    }

    /**
     * الحصول على المؤسسات حسب النوع
     */
    public function getInstitutionsByType(int $typeId, array $filters = [], int $perPage = 15)
    {
        Log::info('📊 Getting institutions by type', [
            'type_id' => $typeId,
            'filters' => $filters
        ]);

        $filters['type_id'] = $typeId;
        return $this->institutionRepository->all($filters, $perPage);
    }

    /**
     * الحصول على المؤسسات حسب المسوق
     */// في InstitutionController.php أو InstitutionService.php

public function getInstitutionsByMarketer($marketerId, $filters = [], $perPage = 15)
{
    $query = Institution::where('created_by_marketer', $marketerId)
        ->with(['type', 'owner']);
    
    // فلتر حسب الحالة
    if (isset($filters['status']) && !empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }
    
    // فلتر حسب البحث
    if (isset($filters['search']) && !empty($filters['search'])) {
        $query->where('name', 'like', "%{$filters['search']}%");
    }
    
    return $query->orderBy('created_at', 'desc')->paginate($perPage);
}

    /**
     * البحث عن المؤسسات
     */
    public function searchInstitutions(string $searchTerm, array $filters = [], int $perPage = 15)
    {
        Log::info('🔍 Searching institutions', [
            'search_term' => $searchTerm,
            'filters' => $filters
        ]);

        $filters['search'] = $searchTerm;
        return $this->institutionRepository->all($filters, $perPage);
    }

    /**
     * تصدير بيانات المؤسسات
     */
    public function exportInstitutions(array $filters = []): array
    {
        Log::info('📤 Exporting institutions', ['filters' => $filters]);

        $institutions = $this->institutionRepository->all($filters, 1000);

        $exportData = $institutions->map(function ($institution) {
            return [
                'ID' => $institution->id,
                'الاسم' => $institution->name,
                'النوع' => $institution->type->name_ar ?? $institution->type->name ?? 'غير محدد',
                'رقم الجوال' => $institution->phone,
                'البريد الإلكتروني' => $institution->email,
                'العنوان' => $institution->address,
                'نسبة الخصم' => $institution->discount_percentage . '%',
                'الحالة' => $this->getStatusArabic($institution->status),
                'تاريخ التسجيل' => $institution->created_at->format('Y-m-d'),
                'تاريخ انتهاء الاتفاقية' => $institution->agreement_expiry_date?->format('Y-m-d') ?? 'غير محدد',
                'عدد المعاملات' => $institution->transactions()->count(),
                'إجمالي الإيرادات' => $institution->transactions()->sum('amount'),
            ];
        });

        return $exportData->toArray();
    }

    /**
     * الحصول على حالة المؤسسة بالعربية
     */
    protected function getStatusArabic(string $status): string
    {
        return match ($status) {
            'active' => 'نشط',
            'pending' => 'قيد الانتظار',
            'suspended' => 'محظور',
            default => $status,
        };
    }

    /**
     * الحصول على المجموع التراكمي للشركة
     */
    protected function getCompanyTotal(): float
    {
        try {
            Log::info('🔍 getCompanyTotal called');
            
            if (!Schema::hasTable('revenue_transactions')) {
                Log::warning('⚠️ revenue_transactions table does not exist');
                return 0;
            }

            $total = (float) RevenueTransaction::orderBy('id', 'desc')->value('total') ?? 0;
            Log::info('📊 Current company total', ['total' => $total]);
            return $total;
        } catch (\Exception $e) {
            Log::error('❌ Error getting company total: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * إنشاء خصم من حساب المؤسسة
     */
    protected function createInstitutionDeduction(Institution $institution): void
    {
        try {
            if (!Schema::hasTable('institution_deductions')) {
                Log::warning('institution_deductions table does not exist, skipping deduction');
                return;
            }

            DB::table('institution_deductions')->insert([
                'institution_id' => $institution->id,
                'amount' => $this->serviceFee,
                'currency' => $this->currency,
                'deduction_type' => 'registration_fee',
                'status' => 'pending',
                'description' => "رسوم تسجيل مؤسسة: {$institution->name}",
                'deducted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('✅ Institution deduction created', [
                'institution_id' => $institution->id,
                'amount' => $this->serviceFee,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create institution deduction: ' . $e->getMessage(), [
                'institution_id' => $institution->id
            ]);
        }
    }

    /**
     * إنشاء عمولة للمسوق مع معاملة الإيرادات
     */
    protected function createMarketerCommissionWithRevenue(Institution $institution, User $marketer): void
    {
        Log::info('📊 START: createMarketerCommissionWithRevenue', [
            'institution_id' => $institution->id,
            'marketer_id' => $marketer->id,
        ]);

        try {
            $commissionAmount = $this->institutionMarketerCommission;
            $serviceFee = $this->serviceFee;
            $netAmount = $serviceFee - $commissionAmount;

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
                'currency' => $this->currency,
                'service_fee' => $serviceFee,
                'customer_discount' => 0,
                'due_date' => now()->addDays(30),
                'notes' => "عمولة تسجيل مؤسسة جديدة - {$institution->name}"
            ]);

            // 2️⃣ تحديث إحصائيات المسوق
            $marketer->increment('institutions_count');
            $marketer->increment('pending_commission', $commissionAmount);
            $marketer->increment('total_commission', $commissionAmount);

            // 3️⃣ الحصول على آخر total
            $previousTotal = $this->getCompanyTotal();
            $newTotal = max(0, $previousTotal - $commissionAmount);

            // 4️⃣ إنشاء معاملة الإيرادات مع الخصم
            $revenueData = [
                'type' => 'institution_registration',
                'gross_amount' => $serviceFee,
                'total_commissions' => $commissionAmount,
                'net_amount' => $netAmount,
                'total' => $newTotal,
                'commission_breakdown' => [
                    'commission_id' => $commission->id,
                    'institution_name' => $institution->name,
                    'institution_id' => $institution->id,
                    'marketer_name' => $marketer->full_name,
                    'marketer_id' => $marketer->id,
                    'commission_amount' => $commissionAmount,
                    'service_fee' => $serviceFee,
                    'net_revenue' => $netAmount,
                    'previous_total' => $previousTotal,
                    'new_total' => $newTotal,
                ],
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'status' => 'completed',
                'currency' => $this->currency,
                'transaction_date' => now(),
                'notes' => "تسجيل مؤسسة جديدة: {$institution->name} - خصم {$commissionAmount} {$this->currency} من total"
            ];

            RevenueTransaction::create($revenueData);

            Log::info('✅ Commission and revenue created successfully', [
                'commission_id' => $commission->id,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ ERROR: Failed to create institution marketer commission and revenue', [
                'error' => $e->getMessage(),
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * الحصول على المؤسسات القريبة (API)
     */
    public function getNearbyInstitutionsApi(float $latitude, float $longitude, float $distance = 10, int $perPage = 15)
    {
        Log::info('📍 Getting nearby institutions', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distance' => $distance
        ]);

        return $this->institutionRepository->getNearby($latitude, $longitude, $distance, $perPage);
    }

    /**
     * الحصول على المؤسسات المميزة
     */
    public function getFeaturedInstitutions(int $limit = 6)
    {
        Log::info('⭐ Getting featured institutions', ['limit' => $limit]);

        return Institution::where('status', 'active')
            ->where('is_featured', true)
            ->orderBy('featured_order', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * تفعيل/تعطيل تمييز المؤسسة
     */
    public function toggleFeatured(int $id): Institution
    {
        Log::info('🔄 Toggling institution featured', ['institution_id' => $id]);

        $institution = $this->institutionRepository->find($id);
        $currentFeatured = $institution->is_featured ?? false;
        
        $institution = $this->institutionRepository->update($id, [
            'is_featured' => !$currentFeatured
        ]);

        Log::info('✅ Featured toggled', [
            'institution_id' => $institution->id,
            'is_featured' => $institution->is_featured
        ]);

        return $institution;
    }

    /**
     * تحديث ترتيب المؤسسات المميزة
     */
    public function updateFeaturedOrder(array $orderData): void
    {
        Log::info('🔄 Updating featured order', ['count' => count($orderData)]);

        DB::transaction(function () use ($orderData) {
            foreach ($orderData as $data) {
                Institution::where('id', $data['id'])
                    ->update(['featured_order' => $data['order']]);
            }
        });

        Log::info('✅ Featured order updated');
    }

    /**
     * الحصول على إحصائيات المؤسسة
     */
    public function getInstitutionStats(int $id): array
    {
        Log::info('📊 Getting institution stats', ['institution_id' => $id]);

        $institution = $this->institutionRepository->find($id);

        return [
            'total_transactions' => $institution->transactions()->count(),
            'total_revenue' => $institution->transactions()->sum('amount'),
            'total_commissions' => Commission::where('institution_id', $id)->sum('amount'),
            'average_transaction_value' => $institution->transactions()->avg('amount') ?? 0,
            'active_customers' => $institution->customers()->where('status', 'active')->count(),
            'total_customers' => $institution->customers()->count(),
            'last_transaction_date' => $institution->transactions()->latest()->first()?->created_at,
            'commission_history' => Commission::where('institution_id', $id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];
    }
}