<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Institution;
use App\Models\DiscountTransaction;
use App\Models\Commission;
use App\Models\RevenueTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialController extends Controller
{
    /**
     * GET /api/financial/stats
     * الإحصائيات المالية العامة
     */
    public function stats(Request $request)
    {
        try {
            $period = $request->get('period', 'monthly');
            $dateRange = $this->getDateRange($period);

            // ✅ إجمالي الإيرادات (من عمود total في revenue_transactions)
            $totalRevenue = (float) RevenueTransaction::where('status', 'completed')
                ->orderBy('id', 'desc')
                ->value('total') ?? 0;

            // ✅ إجمالي العمولات
            $totalCommissions = (float) Commission::sum('amount');

            // ✅ إيرادات الشهر الحالي (من عمود total)
            $monthlyRevenue = (float) RevenueTransaction::where('status', 'completed')
                ->whereBetween('transaction_date', [$dateRange['start'], $dateRange['end']])
                ->orderBy('id', 'desc')
                ->value('total') ?? 0;

            // ✅ إجمالي عدد العمليات (العملاء + المؤسسات)
            $totalCustomers = Customer::count();
            $totalInstitutions = Institution::count();
            $totalTransactions = $totalCustomers + $totalInstitutions;

            // ✅ عمليات الشهر الحالي
            $monthlyCustomers = Customer::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
            $monthlyInstitutions = Institution::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])->count();
            $monthlyTransactions = $monthlyCustomers + $monthlyInstitutions;

            // ✅ عمليات الفترة السابقة
            $previousDateRange = $this->getPreviousDateRange($dateRange);
            $previousCustomers = Customer::whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']])->count();
            $previousInstitutions = Institution::whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']])->count();
            $previousTransactions = $previousCustomers + $previousInstitutions;

            // ✅ حساب النمو
            $transactionsGrowth = $previousTransactions > 0 
                ? (($totalTransactions - $previousTransactions) / $previousTransactions) * 100 
                : 0;

            // ✅ العمولات المدفوعة والمستحقة
            $paidCommissions = (float) Commission::where('status', 'paid')->sum('amount');
            $pendingCommissions = (float) Commission::where('status', 'pending')->sum('amount');

            // ✅ عمولات مسوقي العملاء
            $customerMarketerCommissions = (float) Commission::where('role', 'customer_marketer')->sum('amount');

            // ✅ عمولات مسوقي المؤسسات
            $institutionMarketerCommissions = (float) Commission::where('role', 'institution_marketer')->sum('amount');

            // ✅ حساب النمو للإيرادات
            $previousRevenue = (float) RevenueTransaction::where('status', 'completed')
                ->whereBetween('transaction_date', [$previousDateRange['start'], $previousDateRange['end']])
                ->orderBy('id', 'desc')
                ->value('total') ?? 0;
            
            $revenueGrowth = $previousRevenue > 0 
                ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    // ✅ الإيرادات
                    'total_revenue' => round($totalRevenue, 2),
                    'monthly_revenue' => round($monthlyRevenue, 2),
                    'revenue_growth' => round($revenueGrowth, 1),

                    // ✅ العمليات (العملاء + المؤسسات)
                    'total_transactions' => $totalTransactions,
                    'monthly_transactions' => $monthlyTransactions,
                    'transactions_growth' => round($transactionsGrowth, 1),

                    // ✅ العمولات
                    'total_commissions' => round($totalCommissions, 2),
                    'paid_commissions' => round($paidCommissions, 2),
                    'pending_commissions' => round($pendingCommissions, 2),

                    // ✅ توزيع العمولات
                    'customer_marketer_commissions' => round($customerMarketerCommissions, 2),
                    'institution_marketer_commissions' => round($institutionMarketerCommissions, 2),

                    // ✅ معلومات إضافية
                    'total_customers' => $totalCustomers,
                    'total_institutions' => $totalInstitutions,
                    'currency' => 'YER',
                    'period' => $period,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/financial/chart-data
     * بيانات الرسم البياني للإيرادات والعمليات
     */
    public function chartData(Request $request)
    {
        try {
            $period = $request->get('period', 'monthly');
            $months = $request->get('months', 12);

            $labels = [];
            $revenueData = [];
            $transactionsData = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $labels[] = $date->format('M Y');

                // ✅ الإيرادات الشهرية (من total)
                $revenue = (float) RevenueTransaction::where('status', 'completed')
                    ->whereYear('transaction_date', $date->year)
                    ->whereMonth('transaction_date', $date->month)
                    ->orderBy('id', 'desc')
                    ->value('total') ?? 0;
                $revenueData[] = round($revenue / 1000, 1); // بالآلاف

                // ✅ العمليات الشهرية (عملاء + مؤسسات)
                $customers = Customer::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();
                $institutions = Institution::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();
                $transactionsData[] = $customers + $institutions;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'revenue_data' => $revenueData,
                    'transactions_data' => $transactionsData,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/financial/revenue-distribution
     * توزيع الإيرادات (العمولات)
     */
    public function revenueDistribution(Request $request)
    {
        try {
            // ✅ عمولات مسوقي العملاء
            $customerMarketerCommissions = (float) Commission::where('role', 'customer_marketer')->sum('amount');

            // ✅ عمولات مسوقي المؤسسات
            $institutionMarketerCommissions = (float) Commission::where('role', 'institution_marketer')->sum('amount');

            // ✅ إجمالي العمولات
            $totalCommissions = $customerMarketerCommissions + $institutionMarketerCommissions;

            // ✅ إجمالي الإيرادات
            $totalRevenue = (float) RevenueTransaction::where('status', 'completed')
                ->orderBy('id', 'desc')
                ->value('total') ?? 0;

            // ✅ الأرباح الصافية (الإيرادات - العمولات)
            $netProfit = $totalRevenue - $totalCommissions;

            $data = [];

            // ✅ عمولات مسوقي العملاء
            if ($totalRevenue > 0) {
                $data[] = [
                    'category' => 'عمولات مسوقي العملاء',
                    'value' => round(($customerMarketerCommissions / $totalRevenue) * 100, 1),
                    'amount' => round($customerMarketerCommissions, 2),
                    'color' => '#4CAF50',
                ];
            }

            // ✅ عمولات مسوقي المؤسسات
            if ($totalRevenue > 0) {
                $data[] = [
                    'category' => 'عمولات مسوقي المؤسسات',
                    'value' => round(($institutionMarketerCommissions / $totalRevenue) * 100, 1),
                    'amount' => round($institutionMarketerCommissions, 2),
                    'color' => '#2196F3',
                ];
            }

            // ✅ الأرباح الصافية
            if ($totalRevenue > 0 && $netProfit > 0) {
                $data[] = [
                    'category' => 'أرباح صافية',
                    'value' => round(($netProfit / $totalRevenue) * 100, 1),
                    'amount' => round($netProfit, 2),
                    'color' => '#FF9800',
                ];
            }

            // إذا كانت الإيرادات صفر
            if ($totalRevenue == 0) {
                $data = [
                    [
                        'category' => 'لا توجد بيانات',
                        'value' => 100,
                        'amount' => 0,
                        'color' => '#9E9E9E',
                    ]
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/financial/top-marketers
     * أفضل المسوقين
     */
    public function topMarketers(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);

            $topMarketers = User::whereIn('role', ['customer_marketer', 'institution_marketer'])
                ->withSum('commissions', 'amount')
                ->withCount(['createdCustomers', 'createdInstitutions'])
                ->orderBy('commissions_sum_amount', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($marketer, $index) {
                    $totalCustomers = ($marketer->created_customers_count ?? 0) + ($marketer->created_institutions_count ?? 0);
                    
                    return [
                        'rank' => $index + 1,
                        'name' => $marketer->full_name,
                        'role' => $marketer->role === 'customer_marketer' ? 'مسوق عملاء' : 'مسوق مؤسسات',
                        'customers' => $totalCustomers,
                        'customers_count' => $marketer->created_customers_count ?? 0,
                        'institutions_count' => $marketer->created_institutions_count ?? 0,
                        'commission' => round($marketer->commissions_sum_amount ?? 0, 2),
                        'currency' => 'YER',
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $topMarketers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/financial/recent-transactions
     * آخر المعاملات (العمليات)
     */
    public function recentTransactions(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);

            // ✅ جلب آخر العملاء
            $recentCustomers = Customer::with('user')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'type' => 'customer',
                        'name' => $customer->user->full_name ?? $customer->full_name,
                        'membership_number' => $customer->membership_number,
                        'amount' => 0,
                        'commission' => 0,
                        'status' => 'completed',
                        'time_ago' => $customer->created_at->diffForHumans(),
                        'date' => $customer->created_at->format('Y-m-d H:i'),
                        'icon' => 'person_add',
                        'color' => 'green',
                    ];
                });

            // ✅ جلب آخر المؤسسات
            $recentInstitutions = Institution::with('type')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($institution) {
                    return [
                        'id' => $institution->id,
                        'type' => 'institution',
                        'name' => $institution->name,
                        'amount' => 0,
                        'commission' => 0,
                        'status' => 'completed',
                        'time_ago' => $institution->created_at->diffForHumans(),
                        'date' => $institution->created_at->format('Y-m-d H:i'),
                        'icon' => 'business',
                        'color' => 'blue',
                    ];
                });

            // ✅ جلب آخر العمولات
            $recentCommissions = Commission::with(['user', 'customer', 'institution'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($commission) {
                    $name = $commission->customer?->full_name ?? 
                            $commission->institution?->name ?? 
                            $commission->user?->full_name ?? 'غير معروف';
                    
                    return [
                        'id' => $commission->id,
                        'type' => 'commission',
                        'name' => $name,
                        'amount' => $commission->amount,
                        'commission' => $commission->amount,
                        'status' => $commission->status,
                        'time_ago' => $commission->created_at->diffForHumans(),
                        'date' => $commission->created_at->format('Y-m-d H:i'),
                        'icon' => 'paid',
                        'color' => $commission->status === 'paid' ? 'green' : 'orange',
                        'marketer' => $commission->user?->full_name ?? 'غير معروف',
                    ];
                });

            // ✅ دمج وترتيب النتائج
            $allTransactions = collect($recentCustomers)
                ->concat($recentInstitutions)
                ->concat($recentCommissions)
                ->sortByDesc('date')
                ->take($limit)
                ->values();

            return response()->json([
                'success' => true,
                'data' => $allTransactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/financial/summary
     * ملخص مالي سريع
     */
    public function summary(Request $request)
    {
        try {
            $today = now()->startOfDay();

            // ✅ إيرادات اليوم (من total)
            $todayRevenue = (float) RevenueTransaction::where('status', 'completed')
                ->whereDate('transaction_date', $today)
                ->orderBy('id', 'desc')
                ->value('total') ?? 0;

            // ✅ عمليات اليوم (عملاء + مؤسسات)
            $todayCustomers = Customer::whereDate('created_at', $today)->count();
            $todayInstitutions = Institution::whereDate('created_at', $today)->count();
            $todayTransactions = $todayCustomers + $todayInstitutions;

            // ✅ عمولات اليوم
            $todayCommissions = (float) Commission::whereDate('created_at', $today)->sum('amount');

            // ✅ الشهر الحالي
            $monthStart = now()->startOfMonth();
            $monthRevenue = (float) RevenueTransaction::where('status', 'completed')
                ->whereDate('transaction_date', '>=', $monthStart)
                ->orderBy('id', 'desc')
                ->value('total') ?? 0;
            
            $monthCustomers = Customer::whereDate('created_at', '>=', $monthStart)->count();
            $monthInstitutions = Institution::whereDate('created_at', '>=', $monthStart)->count();
            $monthTransactions = $monthCustomers + $monthInstitutions;
            $monthCommissions = (float) Commission::whereDate('created_at', '>=', $monthStart)->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'today' => [
                        'revenue' => round($todayRevenue, 2),
                        'transactions' => $todayTransactions,
                        'commissions' => round($todayCommissions, 2),
                    ],
                    'this_month' => [
                        'revenue' => round($monthRevenue, 2),
                        'transactions' => $monthTransactions,
                        'commissions' => round($monthCommissions, 2),
                    ],
                    'currency' => 'YER',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/financial/commission-breakdown
     * تفصيل العمولات
     */
    public function commissionBreakdown(Request $request)
    {
        try {
            $period = $request->get('period', 'monthly');
            $dateRange = $this->getDateRange($period);

            // ✅ عمولات مسوقي العملاء
            $customerMarketerCommissions = (float) Commission::where('role', 'customer_marketer')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('amount');

            // ✅ عمولات مسوقي المؤسسات
            $institutionMarketerCommissions = (float) Commission::where('role', 'institution_marketer')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('amount');

            // ✅ العمولات حسب الحالة
            $pendingCommissions = (float) Commission::where('status', 'pending')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('amount');

            $paidCommissions = (float) Commission::where('status', 'paid')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->sum('amount');

            // ✅ العمولات الشهرية
            $monthlyCommissions = Commission::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(amount) as total'),
                DB::raw('SUM(CASE WHEN role = "customer_marketer" THEN amount ELSE 0 END) as customer'),
                DB::raw('SUM(CASE WHEN role = "institution_marketer" THEN amount ELSE 0 END) as institution')
            )
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->map(function ($item) {
                $date = \Carbon\Carbon::create($item->year, $item->month, 1);
                return [
                    'month' => $date->format('M Y'),
                    'total' => round($item->total, 2),
                    'customer_marketer' => round($item->customer, 2),
                    'institution_marketer' => round($item->institution, 2),
                ];
            });

            $totalCommissions = $customerMarketerCommissions + $institutionMarketerCommissions;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_commissions' => round($totalCommissions, 2),
                    'pending_commissions' => round($pendingCommissions, 2),
                    'paid_commissions' => round($paidCommissions, 2),
                    'customer_marketer_commissions' => round($customerMarketerCommissions, 2),
                    'institution_marketer_commissions' => round($institutionMarketerCommissions, 2),
                    'monthly_breakdown' => $monthlyCommissions,
                    'currency' => 'YER',
                    'period' => $period,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== Helper Methods ====================

    /**
     * الحصول على نطاق التواريخ حسب الفترة
     */
    protected function getDateRange(string $period): array
    {
        return match($period) {
            'daily' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'weekly' => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'monthly' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'yearly' => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            default => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()]
        };
    }

    /**
     * الحصول على نطاق التواريخ السابق
     */
    protected function getPreviousDateRange(array $currentRange): array
    {
        $start = \Carbon\Carbon::parse($currentRange['start']);
        $end = \Carbon\Carbon::parse($currentRange['end']);
        $diff = $start->diffInDays($end);

        return [
            'start' => $start->copy()->subDays($diff + 1)->startOfDay(),
            'end' => $end->copy()->subDays($diff + 1)->endOfDay(),
        ];
    }
}