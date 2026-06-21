<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\RevenueTransaction;
use App\Models\Customer;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    protected float $serviceFee = 3000; // 3000 YER
    protected float $customerMarketerCommission = 400; // 400 YER
    protected float $institutionMarketerCommission = 400; // 400 YER
    protected string $defaultCurrency = 'YER';

    /**
     * إنشاء عمولة لمسوق العملاء عند تسجيل عميل جديد
     */
    public function createCustomerRegistrationCommission(Customer $customer, User $marketer): ?Commission
    {
        if (!$marketer->isCustomerMarketer()) {
            Log::warning('المستخدم ليس مسوق عملاء', ['user_id' => $marketer->id]);
            return null;
        }

        return DB::transaction(function () use ($customer, $marketer) {
            // إنشاء العمولة
            $commission = Commission::create([
                'user_id' => $marketer->id,
                'role' => 'customer_marketer',
                'amount' => $this->customerMarketerCommission,
                'commission_percentage' => 0,
                'reason' => "عمولة عن تسجيل عميل جديد: {$customer->full_name} (رقم العضوية: {$customer->membership_number})",
                'customer_id' => $customer->id,
                'status' => 'pending',
                'currency' => $this->defaultCurrency,
                'service_fee' => $this->serviceFee,
                'customer_discount' => 0,
                'due_date' => now()->addDays(30),
            ]);

            // تحديث إحصائيات المسوق
            $marketer->increment('customers_count');
            $marketer->increment('pending_commission', $this->customerMarketerCommission);
            $marketer->increment('total_commission', $this->customerMarketerCommission);

            // ✅ إنشاء معاملة الإيرادات مع إضافة إلى total (للعملاء)
            $this->createRevenueTransactionWithTotal(
                'customer_registration',
                $this->serviceFee,
                $this->customerMarketerCommission,
                $customer->id,
                null,
                $marketer->id,
                [
                    'commission_id' => $commission->id,
                    'customer_name' => $customer->full_name,
                    'membership_number' => $customer->membership_number,
                    'marketer_name' => $marketer->full_name,
                ]
            );

            Log::info('تم إنشاء عمولة تسجيل عميل جديد', [
                'customer_id' => $customer->id,
                'marketer_id' => $marketer->id,
                'amount' => $this->customerMarketerCommission,
            ]);

            return $commission;
        });
    }

    /**
     * إنشاء عمولة لمسوق المؤسسات عند تسجيل مؤسسة جديدة
     */
    public function createInstitutionRegistrationCommission(Institution $institution, User $marketer): ?Commission
    {
        if (!$marketer->isInstitutionMarketer()) {
            Log::warning('المستخدم ليس مسوق مؤسسات', ['user_id' => $marketer->id]);
            return null;
        }

        return DB::transaction(function () use ($institution, $marketer) {
            // إنشاء العمولة
            $commission = Commission::create([
                'user_id' => $marketer->id,
                'role' => 'institution_marketer',
                'amount' => $this->institutionMarketerCommission,
                'commission_percentage' => 0,
                'reason' => "عمولة عن تسجيل مؤسسة جديدة: {$institution->name}",
                'institution_id' => $institution->id,
                'status' => 'pending',
                'currency' => $this->defaultCurrency,
                'service_fee' => $this->serviceFee,
                'customer_discount' => 0,
                'due_date' => now()->addDays(30),
            ]);

            // تحديث إحصائيات المسوق
            $marketer->increment('institutions_count');
            $marketer->increment('pending_commission', $this->institutionMarketerCommission);
            $marketer->increment('total_commission', $this->institutionMarketerCommission);

            // ✅ إنشاء معاملة الإيرادات بدون إضافة إلى total (للمؤسسات)
            $this->createRevenueTransactionWithoutTotal(
                'institution_registration',
                $this->serviceFee,
                $this->institutionMarketerCommission,
                null,
                $institution->id,
                $marketer->id,
                [
                    'commission_id' => $commission->id,
                    'institution_name' => $institution->name,
                    'marketer_name' => $marketer->full_name,
                ]
            );

            Log::info('تم إنشاء عمولة تسجيل مؤسسة جديدة', [
                'institution_id' => $institution->id,
                'marketer_id' => $marketer->id,
                'amount' => $this->institutionMarketerCommission,
            ]);

            return $commission;
        });
    }

    /**
     * إنشاء معاملة إيرادات مع إضافة إلى total (للعملاء فقط)
     */
    protected function createRevenueTransactionWithTotal(
        string $type,
        float $grossAmount,
        float $totalCommissions,
        ?int $customerId = null,
        ?int $institutionId = null,
        ?int $marketerId = null,
        array $breakdown = []
    ): RevenueTransaction {
        $netAmount = $grossAmount - $totalCommissions;
        
        // ✅ حساب المجموع التراكمي للشركة
        $previousTotal = $this->getCompanyTotal();
        $newTotal = $previousTotal + $netAmount;

        return RevenueTransaction::create([
            'type' => $type,
            'gross_amount' => $grossAmount,
            'total_commissions' => $totalCommissions,
            'net_amount' => $netAmount,
            'total' => $newTotal,
            'commission_breakdown' => $breakdown,
            'customer_id' => $customerId,
            'institution_id' => $institutionId,
            'marketer_id' => $marketerId,
            'status' => 'completed',
            'currency' => $this->defaultCurrency,
            'transaction_date' => now(),
            'notes' => "معاملة {$type} بقيمة {$grossAmount} {$this->defaultCurrency}",
        ]);
    }

    /**
     * إنشاء معاملة إيرادات بدون إضافة إلى total (للمؤسسات فقط)
     */
    protected function createRevenueTransactionWithoutTotal(
        string $type,
        float $grossAmount,
        float $totalCommissions,
        ?int $customerId = null,
        ?int $institutionId = null,
        ?int $marketerId = null,
        array $breakdown = []
    ): RevenueTransaction {
        $netAmount = $grossAmount - $totalCommissions;

        return RevenueTransaction::create([
            'type' => $type,
            'gross_amount' => $grossAmount,
            'total_commissions' => $totalCommissions,
            'net_amount' => $netAmount,
            'total' => 0, // ✅ لا نضيف أي مبلغ إلى total
            'commission_breakdown' => $breakdown,
            'customer_id' => $customerId,
            'institution_id' => $institutionId,
            'marketer_id' => $marketerId,
            'status' => 'completed',
            'currency' => $this->defaultCurrency,
            'transaction_date' => now(),
            'notes' => "معاملة {$type} بقيمة {$grossAmount} {$this->defaultCurrency} (بدون إضافة إلى total)",
        ]);
    }

    /**
     * الحصول على المجموع التراكمي للشركة
     */
    protected function getCompanyTotal(): float
    {
        return (float) RevenueTransaction::orderBy('id', 'desc')->value('total') ?? 0;
    }

    /**
     * دفع عمولة
     */
    public function payCommission(Commission $commission): bool
    {
        if ($commission->isPaid()) {
            Log::warning('العمولة مدفوعة بالفعل', ['commission_id' => $commission->id]);
            return false;
        }

        return DB::transaction(function () use ($commission) {
            $commission->markAsPaid();

            // تحديث إحصائيات المسوق
            $user = $commission->user;
            $user->decrement('pending_commission', $commission->amount);
            $user->increment('paid_commission', $commission->amount);

            Log::info('تم دفع العمولة', [
                'commission_id' => $commission->id,
                'user_id' => $user->id,
                'amount' => $commission->amount,
            ]);

            return true;
        });
    }

    /**
     * الحصول على إحصائيات العمولات للمسوق
     */
    public function getMarketerStats(User $user): array
    {
        $totalCommission = Commission::where('user_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount');

        $pendingCommission = Commission::where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount');

        $customerCount = Commission::where('user_id', $user->id)
            ->where('role', 'customer_marketer')
            ->where('status', 'paid')
            ->count();

        $institutionCount = Commission::where('user_id', $user->id)
            ->where('role', 'institution_marketer')
            ->where('status', 'paid')
            ->count();

        return [
            'total_commission' => round($totalCommission, 2),
            'pending_commission' => round($pendingCommission, 2),
            'paid_commission' => round($user->paid_commission ?? 0, 2),
            'customers_count' => $customerCount,
            'institutions_count' => $institutionCount,
            'currency' => $this->defaultCurrency,
        ];
    }

    /**
     * الحصول على إحصائيات الإيرادات العامة
     */
    public function getRevenueStats(): array
    {
        $totalGross = RevenueTransaction::where('status', 'completed')->sum('gross_amount');
        $totalCommissions = RevenueTransaction::where('status', 'completed')->sum('total_commissions');
        $totalNet = RevenueTransaction::where('status', 'completed')->sum('net_amount');

        $customerRegistrations = RevenueTransaction::where('type', 'customer_registration')
            ->where('status', 'completed')
            ->count();

        $institutionRegistrations = RevenueTransaction::where('type', 'institution_registration')
            ->where('status', 'completed')
            ->count();

        $todayGross = RevenueTransaction::where('status', 'completed')
            ->whereDate('transaction_date', today())
            ->sum('gross_amount');

        $todayNet = RevenueTransaction::where('status', 'completed')
            ->whereDate('transaction_date', today())
            ->sum('net_amount');

        return [
            'total_gross' => round($totalGross, 2),
            'total_commissions' => round($totalCommissions, 2),
            'total_net' => round($totalNet, 2),
            'customer_registrations' => $customerRegistrations,
            'institution_registrations' => $institutionRegistrations,
            'today_gross' => round($todayGross, 2),
            'today_net' => round($todayNet, 2),
            'currency' => $this->defaultCurrency,
            'commission_rate' => $totalGross > 0 
                ? round(($totalCommissions / $totalGross) * 100, 2) 
                : 0,
        ];
    }

    /**
     * الحصول على جميع العمولات مع التصفية
     */
    public function getCommissions(array $filters = [], int $perPage = 15)
    {
        $query = Commission::with(['user', 'customer', 'institution']);

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * الحصول على جميع معاملات الإيرادات مع التصفية
     */
    public function getRevenueTransactions(array $filters = [], int $perPage = 15)
    {
        $query = RevenueTransaction::with(['customer', 'institution', 'marketer']);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['to_date']);
        }

        return $query->orderBy('transaction_date', 'desc')->paginate($perPage);
    }

    /**
     * الحصول على تفاصيل العمولة
     */
    public function getCommissionDetails(Commission $commission): array
    {
        return [
            'id' => $commission->id,
            'marketer_name' => $commission->user->full_name,
            'marketer_role' => $commission->role,
            'amount' => $commission->amount,
            'status' => $commission->status,
            'reason' => $commission->reason,
            'service_fee' => $commission->service_fee,
            'customer_discount' => $commission->customer_discount,
            'net_amount' => $commission->service_fee - $commission->customer_discount - $commission->amount,
            'created_at' => $commission->created_at,
            'paid_at' => $commission->paid_at,
            'customer' => $commission->customer ? [
                'id' => $commission->customer->id,
                'name' => $commission->customer->full_name,
                'membership_number' => $commission->customer->membership_number,
            ] : null,
            'institution' => $commission->institution ? [
                'id' => $commission->institution->id,
                'name' => $commission->institution->name,
            ] : null,
        ];
    }

    /**
     * الحصول على تقرير العمولات الشهري
     */
    public function getMonthlyReport(int $year, int $month): array
    {
        $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
        $endDate = now()->setYear($year)->setMonth($month)->endOfMonth();

        $commissions = Commission::whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalCustomer = $commissions->where('role', 'customer_marketer')->sum('amount');
        $totalInstitution = $commissions->where('role', 'institution_marketer')->sum('amount');

        $revenue = RevenueTransaction::whereBetween('transaction_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->get();

        $totalGross = $revenue->sum('gross_amount');
        $totalNet = $revenue->sum('net_amount');

        return [
            'month' => $month,
            'year' => $year,
            'total_commissions' => round($totalCustomer + $totalInstitution, 2),
            'customer_marketer_commissions' => round($totalCustomer, 2),
            'institution_marketer_commissions' => round($totalInstitution, 2),
            'total_gross_revenue' => round($totalGross, 2),
            'total_net_revenue' => round($totalNet, 2),
            'currency' => $this->defaultCurrency,
            'details' => $commissions->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'marketer' => $commission->user->full_name,
                    'role' => $commission->role,
                    'amount' => $commission->amount,
                    'status' => $commission->status,
                    'created_at' => $commission->created_at->toDateString(),
                ];
            }),
        ];
    }
}