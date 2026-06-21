<?php

namespace App\Services;

use App\Contracts\Repositories\InstitutionRepositoryInterface;
use App\Models\Institution;
use App\Models\User;
use App\Models\Commission;
use App\Models\RevenueTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * إنشاء مؤسسة جديدة مع عمولة لمسوق المؤسسات
     */
    // public function createInstitution(array $data, int $marketerId): Institution
    // {
    //     Log::info('🚀 START: createInstitution called', [
    //         'marketer_id' => $marketerId,
    //         'data_keys' => array_keys($data)
    //     ]);

    //     return DB::transaction(function () use ($data, $marketerId) {
    //         Log::info('🔄 Creating institution with data:', $data);
            
    //         // إضافة بيانات المسوق
    //         $data['created_by_marketer'] = $marketerId;
    //         $data['status'] = 'active';
            
    //         // إنشاء المؤسسة
    //         $institution = $this->institutionRepository->create($data);
            
    //         Log::info('✅ Institution created successfully', [
    //             'institution_id' => $institution->id,
    //             'name' => $institution->name,
    //             'marketer_id' => $marketerId
    //         ]);

    //         // ✅ إنشاء عمولة لمسوق المؤسسات (400 YER) ومعاملة الإيرادات
    //         $marketer = User::find($marketerId);
            
    //         Log::info('🔍 Checking marketer', [
    //             'marketer_id' => $marketerId,
    //             'marketer_found' => $marketer ? 'Yes' : 'No',
    //             'is_institution_marketer' => $marketer ? $marketer->isInstitutionMarketer() : false,
    //             'marketer_role' => $marketer ? $marketer->role : 'N/A'
    //         ]);

    //         if ($marketer && $marketer->isInstitutionMarketer()) {
    //             Log::info('✅ Marketer is valid, creating commission...');
    //             $this->createMarketerCommissionWithRevenue($institution, $marketer);
    //         } else {
    //             Log::warning('⚠️ Invalid marketer for institution creation', [
    //                 'marketer_id' => $marketerId,
    //                 'institution_id' => $institution->id,
    //                 'is_marketer' => $marketer?->isInstitutionMarketer() ?? false,
    //                 'marketer_role' => $marketer?->role ?? 'N/A'
    //             ]);
    //         }

    //         Log::info('🏁 END: createInstitution completed');
    //         return $institution;
    //     });
    // }

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

            // 3️⃣ ✅ الحصول على آخر total
            $lastRecord = RevenueTransaction::orderBy('id', 'desc')->first();
            $previousTotal = $lastRecord ? (float) $lastRecord->total : 0;
            $newTotal = max(0, $previousTotal - $commissionAmount);

            // 4️⃣ ✅ إنشاء معاملة الإيرادات مع الخصم
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
     * ✅ إنشاء عمولة للمسوق (400 ريال يمني) ومعاملة الإيرادات مع خصم من total
     */
    protected function createMarketerCommissionWithRevenue(Institution $institution, User $marketer): void
    {
        Log::info('📊 START: createMarketerCommissionWithRevenue', [
            'institution_id' => $institution->id,
            'marketer_id' => $marketer->id,
        ]);

        try {
            $commissionAmount = $this->institutionMarketerCommission; // 400 YER
            $serviceFee = $this->serviceFee; // 3000 YER
            $netAmount = $serviceFee - $commissionAmount; // 2600 YER

            Log::info('📊 Commission values', [
                'commission_amount' => $commissionAmount,
                'service_fee' => $serviceFee,
                'net_amount' => $netAmount,
                'currency' => $this->currency,
            ]);

            // 1️⃣ إنشاء العمولة
            Log::info('🔄 1. Creating commission...');
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

            Log::info('✅ 1. Commission created', [
                'commission_id' => $commission->id,
                'amount' => $commissionAmount,
            ]);

            // 2️⃣ تحديث إحصائيات المسوق
            Log::info('🔄 2. Updating marketer stats...');
            $marketer->increment('institutions_count');
            $marketer->increment('pending_commission', $commissionAmount);
            $marketer->increment('total_commission', $commissionAmount);

            Log::info('✅ 2. Marketer stats updated', [
                'marketer_id' => $marketer->id,
                'institutions_count' => $marketer->institutions_count,
                'pending_commission' => $marketer->pending_commission,
                'total_commission' => $marketer->total_commission,
            ]);

            // 3️⃣ ✅ الحصول على آخر total
            Log::info('🔄 3. Getting company total...');
            $previousTotal = $this->getCompanyTotal();
            $newTotal = max(0, $previousTotal - $commissionAmount); // ✅ نخصم 400 ريال

            Log::info('📊 3. Total calculation', [
                'previous_total' => $previousTotal,
                'deduction_amount' => $commissionAmount,
                'new_total' => $newTotal,
            ]);

            // 4️⃣ ✅ إنشاء معاملة الإيرادات مع الخصم
            Log::info('🔄 4. Creating revenue transaction...');
            
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

            Log::info('📊 Revenue data to insert:', $revenueData);

            $revenueTransaction = RevenueTransaction::create($revenueData);

            Log::info('✅ 4. Revenue transaction created', [
                'transaction_id' => $revenueTransaction->id,
                'institution_id' => $institution->id,
                'gross_amount' => $serviceFee,
                'commission' => $commissionAmount,
                'net_amount' => $netAmount,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
            ]);

            Log::info('🏁 END: createMarketerCommissionWithRevenue completed successfully');

        } catch (\Exception $e) {
            Log::error('❌ ERROR: Failed to create institution marketer commission and revenue', [
                'error' => $e->getMessage(),
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'trace' => $e->getTraceAsString()
            ]);
            // لا نريد أن يفشل إنشاء المؤسسة بسبب فشل إنشاء العمولة
        }
    }

    /**
     * ✅ الحصول على المجموع التراكمي للشركة من جدول revenue_transactions
     */
    protected function getCompanyTotal(): float
    {
        try {
            Log::info('🔍 getCompanyTotal called');
            
            // ✅ التحقق من وجود جدول revenue_transactions
            if (!\Illuminate\Support\Facades\Schema::hasTable('revenue_transactions')) {
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
     * إنشاء خصم من حساب المؤسسة (3000 ريال يمني)
     */
    protected function createInstitutionDeduction(Institution $institution): void
    {
        try {
            // التحقق من وجود الجدول
            if (!\Illuminate\Support\Facades\Schema::hasTable('institution_deductions')) {
                Log::warning('institution_deductions table does not exist, skipping deduction');
                return;
            }

            \Illuminate\Support\Facades\DB::table('institution_deductions')->insert([
                'institution_id' => $institution->id,
                'amount' => $this->serviceFee, // 3000 YER
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

    // ... باقي الدوال كما هي (updateInstitution, deleteInstitution, renewAgreement, etc.)
}