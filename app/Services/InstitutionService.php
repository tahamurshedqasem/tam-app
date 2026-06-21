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
    public function createInstitution(array $data, int $marketerId): Institution
    {
        return DB::transaction(function () use ($data, $marketerId) {
            Log::info('Creating institution with data:', $data);
            
            // إضافة بيانات المسوق
            $data['created_by_marketer'] = $marketerId;
            $data['status'] = 'active';
            
            // إنشاء المؤسسة
            $institution = $this->institutionRepository->create($data);
            
            Log::info('Institution created successfully', [
                'institution_id' => $institution->id,
                'name' => $institution->name,
                'marketer_id' => $marketerId
            ]);

            // إنشاء عمولة لمسوق المؤسسات (400 YER) ومعاملة الإيرادات
            $marketer = User::find($marketerId);
            if ($marketer && $marketer->isInstitutionMarketer()) {
                $this->createMarketerCommissionWithRevenue($institution, $marketer);
            } else {
                Log::warning('Invalid marketer for institution creation', [
                    'marketer_id' => $marketerId,
                    'institution_id' => $institution->id,
                    'is_marketer' => $marketer?->isInstitutionMarketer() ?? false
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
        try {
            $commissionAmount = $this->institutionMarketerCommission; // 400 YER
            $serviceFee = $this->serviceFee; // 3000 YER
            $netAmount = $serviceFee - $commissionAmount; // 2600 YER

            Log::info('🔄 Creating institution marketer commission with deduction', [
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'commission_amount' => $commissionAmount,
                'service_fee' => $serviceFee,
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
                'currency' => $this->currency,
                'service_fee' => $serviceFee,
                'customer_discount' => 0,
                'due_date' => now()->addDays(30),
                'notes' => "عمولة تسجيل مؤسسة جديدة - {$institution->name}"
            ]);

            Log::info('✅ Commission created', [
                'commission_id' => $commission->id,
                'amount' => $commissionAmount,
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

            // 3️⃣ ✅ الحصول على آخر total وخصم قيمة العمولة
            $previousTotal = $this->getCompanyTotal();
            $newTotal = max(0, $previousTotal - $commissionAmount); // ✅ نخصم 400 ريال

            Log::info('📊 Total calculation for deduction', [
                'previous_total' => $previousTotal,
                'deduction_amount' => $commissionAmount,
                'new_total' => $newTotal,
            ]);

            // 4️⃣ ✅ إنشاء معاملة الإيرادات مع الخصم
            $revenueTransaction = RevenueTransaction::create([
                'type' => 'institution_registration',
                'gross_amount' => $serviceFee,
                'total_commissions' => $commissionAmount,
                'net_amount' => $netAmount,
                'total' => $newTotal, // ✅ total الجديد بعد الخصم
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
            ]);

            Log::info('✅ Revenue transaction created with deduction', [
                'transaction_id' => $revenueTransaction->id,
                'institution_id' => $institution->id,
                'gross_amount' => $serviceFee,
                'commission' => $commissionAmount,
                'net_amount' => $netAmount,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
            ]);

            // 5️⃣ (اختياري) إنشاء خصم من حساب المؤسسة
            $this->createInstitutionDeduction($institution);

        } catch (\Exception $e) {
            Log::error('❌ Failed to create institution marketer commission and revenue: ' . $e->getMessage(), [
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
        $total = (float) RevenueTransaction::orderBy('id', 'desc')->value('total') ?? 0;
        Log::info('📊 Current company total', ['total' => $total]);
        return $total;
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

    /**
     * تحديث بيانات المؤسسة
     */
    public function updateInstitution(Institution $institution, array $data): Institution
    {
        return DB::transaction(function () use ($institution, $data) {
            Log::info('Updating institution', [
                'institution_id' => $institution->id,
                'data' => $data
            ]);

            $this->institutionRepository->update($institution->id, $data);
            
            Log::info('Institution updated successfully', [
                'institution_id' => $institution->id
            ]);

            return $institution->fresh();
        });
    }

    /**
     * حذف المؤسسة
     */
    public function deleteInstitution(Institution $institution): bool
    {
        return DB::transaction(function () use ($institution) {
            Log::info('Deleting institution', [
                'institution_id' => $institution->id,
                'name' => $institution->name
            ]);

            $result = $this->institutionRepository->delete($institution->id);
            
            Log::info('Institution deleted successfully', [
                'institution_id' => $institution->id,
                'result' => $result
            ]);

            return $result;
        });
    }

    /**
     * تجديد اتفاقية المؤسسة مع إنشاء عمولة تجديد
     */
    public function renewAgreement(Institution $institution, int $months = 12): Institution
    {
        return DB::transaction(function () use ($institution, $months) {
            Log::info('Renewing institution agreement', [
                'institution_id' => $institution->id,
                'months' => $months
            ]);

            $institution->renewAgreement($months);
            
            // إنشاء عمولة تجديد لمسوق المؤسسات
            $marketer = User::find($institution->created_by_marketer);
            if ($marketer && $marketer->isInstitutionMarketer()) {
                $this->createRenewalCommission($institution, $marketer);
            }

            Log::info('Institution agreement renewed successfully', [
                'institution_id' => $institution->id,
                'new_expiry_date' => $institution->agreement_expiry_date
            ]);

            return $institution->fresh();
        });
    }

    /**
     * إنشاء عمولة تجديد للمؤسسة (مع خصم من total)
     */
    protected function createRenewalCommission(Institution $institution, User $marketer): void
    {
        try {
            $commissionAmount = $this->institutionMarketerCommission; // 400 YER
            $serviceFee = $this->serviceFee; // 3000 YER
            $netAmount = $serviceFee - $commissionAmount; // 2600 YER

            // إنشاء العمولة
            $commission = Commission::create([
                'user_id' => $marketer->id,
                'role' => 'institution_marketer',
                'amount' => $commissionAmount,
                'commission_percentage' => 0,
                'reason' => "عمولة عن تجديد اتفاقية مؤسسة: {$institution->name}",
                'institution_id' => $institution->id,
                'transaction_id' => null,
                'status' => 'pending',
                'currency' => $this->currency,
                'service_fee' => $serviceFee,
                'customer_discount' => 0,
                'due_date' => now()->addDays(30),
                'notes' => "عمولة تجديد اتفاقية مؤسسة - {$institution->name}"
            ]);

            // تحديث إحصائيات المسوق
            $marketer->increment('pending_commission', $commissionAmount);
            $marketer->increment('total_commission', $commissionAmount);

            // ✅ الحصول على آخر total وخصم قيمة العمولة
            $previousTotal = $this->getCompanyTotal();
            $newTotal = max(0, $previousTotal - $commissionAmount);

            // ✅ إنشاء معاملة الإيرادات مع الخصم
            RevenueTransaction::create([
                'type' => 'renewal',
                'gross_amount' => $serviceFee,
                'total_commissions' => $commissionAmount,
                'net_amount' => $netAmount,
                'total' => $newTotal,
                'commission_breakdown' => [
                    'commission_id' => $commission->id,
                    'institution_name' => $institution->name,
                    'institution_id' => $institution->id,
                    'marketer_name' => $marketer->full_name,
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
                'notes' => "تجديد اتفاقية مؤسسة: {$institution->name} - خصم {$commissionAmount} {$this->currency} من total"
            ]);

            Log::info('✅ Renewal commission created with deduction', [
                'commission_id' => $commission->id,
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'amount' => $commissionAmount,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create renewal commission: ' . $e->getMessage(), [
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id
            ]);
        }
    }

    /**
     * تحديث نسبة الخصم للمؤسسة
     */
    public function updateDiscountPercentage(Institution $institution, float $percentage): Institution
    {
        Log::info('Updating discount percentage', [
            'institution_id' => $institution->id,
            'new_percentage' => $percentage,
            'old_percentage' => $institution->discount_percentage
        ]);

        $institution->updateDiscountPercentage($percentage);
        
        Log::info('Discount percentage updated successfully', [
            'institution_id' => $institution->id,
            'new_percentage' => $percentage
        ]);

        return $institution->fresh();
    }

    /**
     * إضافة مالك للمؤسسة
     */
    public function addOwner(Institution $institution, User $user, bool $isPrimary = false): Institution
    {
        Log::info('Adding owner to institution', [
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'is_primary' => $isPrimary
        ]);

        $institution->addOwner($user, $isPrimary);
        
        Log::info('Owner added successfully', [
            'institution_id' => $institution->id,
            'user_id' => $user->id
        ]);

        return $institution->fresh();
    }

    /**
     * إزالة مالك من المؤسسة
     */
    public function removeOwner(Institution $institution, User $user): Institution
    {
        Log::info('Removing owner from institution', [
            'institution_id' => $institution->id,
            'user_id' => $user->id
        ]);

        $institution->removeOwner($user);
        
        Log::info('Owner removed successfully', [
            'institution_id' => $institution->id,
            'user_id' => $user->id
        ]);

        return $institution->fresh();
    }

    /**
     * الحصول على إحصائيات المؤسسة
     */
    public function getInstitutionStats(Institution $institution): array
    {
        $totalDiscounts = $institution->discountTransactions()->sum('amount_saved');
        $totalTransactions = $institution->discountTransactions()->count();
        $totalCustomers = $institution->customers()->count();
        
        // إحصائيات العمولات للمؤسسة
        $totalCommissions = Commission::where('institution_id', $institution->id)->sum('amount');
        $pendingCommissions = Commission::where('institution_id', $institution->id)
            ->where('status', 'pending')
            ->sum('amount');
        $paidCommissions = Commission::where('institution_id', $institution->id)
            ->where('status', 'paid')
            ->sum('amount');

        return [
            'total_discounts' => $totalDiscounts,
            'total_transactions' => $totalTransactions,
            'total_customers' => $totalCustomers,
            'total_commissions' => round($totalCommissions, 2),
            'pending_commissions' => round($pendingCommissions, 2),
            'paid_commissions' => round($paidCommissions, 2),
            'discount_percentage' => $institution->discount_percentage,
            'agreement_days_remaining' => $institution->agreementDaysRemaining(),
            'agreement_status' => $institution->agreement_status,
            'service_fee' => $this->serviceFee,
            'currency' => $this->currency,
        ];
    }

    /**
     * الحصول على تقرير الإيرادات للمؤسسة
     */
    public function getInstitutionRevenueReport(Institution $institution): array
    {
        $revenueTransactions = RevenueTransaction::where('institution_id', $institution->id)
            ->where('status', 'completed')
            ->get();

        $totalGross = $revenueTransactions->sum('gross_amount');
        $totalCommissions = $revenueTransactions->sum('total_commissions');
        $totalNet = $revenueTransactions->sum('net_amount');

        return [
            'institution_name' => $institution->name,
            'total_gross_revenue' => round($totalGross, 2),
            'total_commissions' => round($totalCommissions, 2),
            'total_net_revenue' => round($totalNet, 2),
            'transactions_count' => $revenueTransactions->count(),
            'currency' => $this->currency,
            'details' => $revenueTransactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'gross_amount' => $transaction->gross_amount,
                    'commission' => $transaction->total_commissions,
                    'net_amount' => $transaction->net_amount,
                    'date' => $transaction->transaction_date?->toDateString(),
                ];
            }),
        ];
    }
}