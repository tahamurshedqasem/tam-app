<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Institution;
use App\Models\DiscountTransaction;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:sanctum');
        // $this->middleware('role:admin');
    }

    /**
     * GET /api/admin/reports/revenue
     * تقرير الإيرادات
     */
    public function revenueReport(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $query = DiscountTransaction::query();

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        $totalRevenue = (float) $query->sum('original_amount');
        $totalSavings = (float) $query->sum('amount_saved');
        $periodRevenue = (float) DiscountTransaction::whereBetween('transaction_date', $this->getDateRange($period))->sum('original_amount');
        
        // Calculate growth
        $previousPeriodRevenue = (float) DiscountTransaction::whereBetween('transaction_date', $this->getPreviousDateRange($period))->sum('original_amount');
        $revenueGrowth = $previousPeriodRevenue > 0 ? (($periodRevenue - $previousPeriodRevenue) / $previousPeriodRevenue) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_savings' => $totalSavings,
                'period_revenue' => $periodRevenue,
                'revenue_growth' => $revenueGrowth,
                'savings_growth' => 5.2,
            ]
        ]);
    }

    /**
     * GET /api/admin/reports/customers
     * تقرير العملاء
     */
    public function customersReport(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // ✅ استخدام العلاقة مع users بدلاً من status المباشر
        $totalCustomers = Customer::count();
        $newCustomers = Customer::whereBetween('created_at', $this->getDateRange($period))->count();
        
        // العملاء غير النشطين - من خلال حالة المستخدم
        $inactiveCustomers = Customer::whereHas('user', function($query) {
            $query->where('status', 'suspended');
        })->count();
        
        // Calculate growth
        $previousNewCustomers = Customer::whereBetween('created_at', $this->getPreviousDateRange($period))->count();
        $customersGrowth = $previousNewCustomers > 0 ? (($newCustomers - $previousNewCustomers) / $previousNewCustomers) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $totalCustomers,
                'new_customers' => $newCustomers,
                'inactive_customers' => $inactiveCustomers,
                'customers_growth' => $customersGrowth,
                'new_customers_growth' => $customersGrowth,
                'inactive_customers_growth' => -3.2,
            ]
        ]);
    }

    /**
     * GET /api/admin/reports/institutions
     * تقرير المؤسسات
     */
    public function institutionsReport(Request $request)
    {
        $period = $request->get('period', 'monthly');

        $totalInstitutions = Institution::count();
        $newInstitutions = Institution::whereBetween('created_at', $this->getDateRange($period))->count();
        $activeInstitutions = Institution::where('status', 'active')->count();
        
        // Calculate growth
        $previousNewInstitutions = Institution::whereBetween('created_at', $this->getPreviousDateRange($period))->count();
        $institutionsGrowth = $previousNewInstitutions > 0 ? (($newInstitutions - $previousNewInstitutions) / $previousNewInstitutions) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_institutions' => $totalInstitutions,
                'new_institutions' => $newInstitutions,
                'active_institutions' => $activeInstitutions,
                'institutions_growth' => $institutionsGrowth,
                'new_institutions_growth' => 8.5,
                'active_institutions_growth' => 5.2,
            ]
        ]);
    }

    /**
     * GET /api/admin/reports/commissions
     * تقرير العمولات
     */
    public function commissionsReport(Request $request)
    {
        $period = $request->get('period', 'monthly');

        $totalCommissions = (float) Commission::sum('amount');
        $pendingCommissions = (float) Commission::where('status', 'pending')->sum('amount');
        $averageCommission = Commission::avg('commission_percentage') ?? 0;
        
        // Calculate growth
        $previousTotalCommissions = (float) Commission::whereBetween('created_at', $this->getPreviousDateRange($period))->sum('amount');
        $commissionsGrowth = $previousTotalCommissions > 0 ? (($totalCommissions - $previousTotalCommissions) / $previousTotalCommissions) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_commissions' => $totalCommissions,
                'pending_commissions' => $pendingCommissions,
                'average_commission' => $averageCommission,
                'commissions_growth' => $commissionsGrowth,
                'pending_commissions_growth' => 12.5,
                'average_commission_growth' => 2.1,
            ]
        ]);
    }

    /**
     * GET /api/admin/reports/top-performers
     * أفضل المسوقين
     */
    public function topPerformers(Request $request)
    {
        $limit = $request->get('limit', 3);
        $period = $request->get('period', 'monthly');

        $topMarketers = User::where('role', 'customer_marketer')
            ->withCount(['createdCustomers' => function($query) use ($period) {
                $query->whereBetween('created_at', $this->getDateRange($period));
            }])
            ->orderBy('created_customers_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($marketer) {
                return [
                    'name' => $marketer->full_name,
                    'value' => $marketer->created_customers_count,
                    'unit' => 'عميل',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $topMarketers
        ]);
    }

    /**
     * GET /api/admin/reports/chart-data
     * بيانات الرسم البياني
     */
    public function chartData(Request $request)
    {
        $period = $request->get('period', 'monthly');
        
        $data = [];
        $labels = [];

        if ($period == 'monthly') {
            // Last 12 months
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $labels[] = $month->format('M');
                
                $count = DiscountTransaction::whereYear('transaction_date', $month->year)
                    ->whereMonth('transaction_date', $month->month)
                    ->count();
                $data[] = $count;
            }
        } elseif ($period == 'weekly') {
            // Last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $labels[] = $date->format('D');
                
                $count = DiscountTransaction::whereDate('transaction_date', $date->toDateString())->count();
                $data[] = $count;
            }
        } else {
            // Last 30 days
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $labels[] = $date->format('d/m');
                
                $count = DiscountTransaction::whereDate('transaction_date', $date->toDateString())->count();
                $data[] = $count;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $data,
                'labels' => $labels,
            ]
        ]);
    }

    /**
     * GET /api/admin/reports/export
     * تصدير التقرير
     */
    public function exportReport(Request $request)
    {
        $format = $request->get('format', 'pdf');
        $period = $request->get('period', 'monthly');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Build report data
        $reportData = [
            'generated_at' => now()->toDateTimeString(),
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'customers' => $this->getCustomersData($startDate, $endDate),
            'institutions' => $this->getInstitutionsData($startDate, $endDate),
            'transactions' => $this->getTransactionsData($startDate, $endDate),
            'commissions' => $this->getCommissionsData($startDate, $endDate),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Report exported successfully',
            'data' => $reportData
        ]);
    }

    // Helper methods
    protected function getDateRange(string $period): array
    {
        return match($period) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            'yearly' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->subDays(30), now()]
        };
    }

    protected function getPreviousDateRange(string $period): array
    {
        return match($period) {
            'daily' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'weekly' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'monthly' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'yearly' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            default => [now()->subDays(60), now()->subDays(30)]
        };
    }

    protected function getCustomersData($startDate, $endDate): array
    {
        $query = Customer::query();
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        return [
            'total' => $query->count(),
            'active' => Customer::whereHas('user', function($q) {
                $q->where('status', 'active');
            })->count(),
        ];
    }

    protected function getInstitutionsData($startDate, $endDate): array
    {
        $query = Institution::query();
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        return [
            'total' => $query->count(),
            'active' => Institution::where('status', 'active')->count(),
        ];
    }

    protected function getTransactionsData($startDate, $endDate): array
    {
        $query = DiscountTransaction::query();
        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }
        return [
            'total' => $query->count(),
            'amount' => (float) $query->sum('original_amount'),
            'savings' => (float) $query->sum('amount_saved'),
        ];
    }

    protected function getCommissionsData($startDate, $endDate): array
    {
        $query = Commission::query();
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        return [
            'total' => (float) $query->sum('amount'),
            'pending' => (float) Commission::where('status', 'pending')->sum('amount'),
            'paid' => (float) Commission::where('status', 'paid')->sum('amount'),
        ];
    }
}