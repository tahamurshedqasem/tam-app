<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Institution;
use App\Models\Commission;
use App\Models\RevenueTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InstitutionMarketerDashboardController extends Controller
{
    /**
     * Constructor - Apply middleware
     */
    public function __construct()
    {
        // $this->middleware('auth:sanctum');
        // $this->middleware('check.status');
        // $this->middleware('role:institution_marketer,admin');
    }

    /**
     * GET /api/institution-marketer/dashboard/stats
     * Get dashboard statistics for institution marketer
     */
    public function dashboardStats(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // Check if user is an institution marketer
            if (!$user->isInstitutionMarketer() && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. User is not an institution marketer.'
                ], 403);
            }

            // If admin, they can view all stats or specific marketer
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'institution_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            // Get statistics
            $stats = $this->getMarketerStats($marketerId);

            // Calculate growth percentages
            $institutionsGrowth = $this->calculateInstitutionsGrowth($marketerId);
            $activeGrowth = $this->calculateActiveGrowth($marketerId);
            $monthlyGrowth = $this->calculateMonthlyGrowth($marketerId);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_institutions' => $stats['total_institutions'],
                    'active_institutions' => $stats['active_institutions'],
                    'expired_institutions' => $stats['expired_institutions'],
                    'pending_institutions' => $stats['pending_institutions'],
                    'monthly_new' => $stats['monthly_new'],
                    'total_commissions' => $stats['total_commissions'],
                    'pending_commissions' => $stats['pending_commissions'],
                    'paid_commissions' => $stats['paid_commissions'],
                    'total_revenue' => $stats['total_revenue'],
                    'institutions_growth' => $institutionsGrowth,
                    'active_growth' => $activeGrowth,
                    'monthly_growth' => $monthlyGrowth,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in dashboardStats: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institution-marketer/dashboard/chart-data
     * Get weekly chart data for institution marketer
     */
    public function getChartData(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'institution_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            // Get weekly data
            $weeklyData = $this->getWeeklyData($marketerId);

            return response()->json([
                'success' => true,
                'data' => $weeklyData
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getChartData: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load chart data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institution-marketer/dashboard/institutions
     * Get recent institutions for the marketer
     */
    public function getInstitutions(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;
            $limit = $request->get('limit', 10);

            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'institution_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $institutions = Institution::where('created_by_marketer', $marketerId)
                ->with(['type', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($institution) {
                    return [
                        'id' => $institution->id,
                        'name' => $institution->name,
                        'type_name' => $institution->type ? $institution->type->name : 'غير محدد',
                        'city' => $institution->city,
                        'address' => $institution->address,
                        'status' => $institution->status,
                        'created_at' => $institution->created_at ? $institution->created_at->toISOString() : null,
                        'user' => $institution->user ? [
                            'id' => $institution->user->id,
                            'full_name' => $institution->user->full_name,
                            'phone' => $institution->user->phone,
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $institutions
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getInstitutions: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load institutions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institution-marketer/dashboard/me
     * Get current marketer profile with stats
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->isInstitutionMarketer() && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. User is not an institution marketer.'
                ], 403);
            }

            $marketerId = $user->id;
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'institution_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
                $user = $marketer;
            }

            $stats = $this->getMarketerStats($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'name' => $user->full_name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'status' => $user->status,
                    'region' => $user->region,
                    'role' => $user->role,
                    'institutions_count' => $stats['total_institutions'],
                    'active_institutions' => $stats['active_institutions'],
                    'total_commission' => $stats['total_commissions'],
                    'pending_commission' => $stats['pending_commissions'],
                    'paid_commission' => $stats['paid_commissions'],
                    'total_revenue' => $stats['total_revenue'],
                    'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                    'updated_at' => $user->updated_at ? $user->updated_at->toISOString() : null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in me: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load marketer profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institution-marketer/dashboard/commission-stats
     * Get commission statistics for the marketer
     */
    public function getCommissionStats(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'institution_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $stats = $this->getMarketerStats($marketerId);

            // Get monthly breakdown
            $monthlyCommissions = Commission::where('user_id', $marketerId)
                ->where('role', 'institution_marketer')
                ->where('status', 'paid')
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(amount) as total')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_commission' => $stats['total_commissions'],
                    'pending_commission' => $stats['pending_commissions'],
                    'paid_commission' => $stats['paid_commissions'],
                    'institutions_count' => $stats['total_institutions'],
                    'active_institutions' => $stats['active_institutions'],
                    'total_revenue' => $stats['total_revenue'],
                    'currency' => 'YER',
                    'monthly_breakdown' => $monthlyCommissions->map(function ($item) {
                        return [
                            'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                            'total' => (float) $item->total,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getCommissionStats: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load commission statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/institution-marketer/dashboard/summary
     * Get a summary for the marketer dashboard
     */
    public function getSummary(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'institution_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $stats = $this->getMarketerStats($marketerId);
            $weeklyData = $this->getWeeklyData($marketerId);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_institutions' => $stats['total_institutions'],
                    'active_institutions' => $stats['active_institutions'],
                    'expired_institutions' => $stats['expired_institutions'],
                    'pending_institutions' => $stats['pending_institutions'],
                    'monthly_new' => $stats['monthly_new'],
                    'total_commissions' => $stats['total_commissions'],
                    'pending_commissions' => $stats['pending_commissions'],
                    'paid_commissions' => $stats['paid_commissions'],
                    'total_revenue' => $stats['total_revenue'],
                    'weekly_chart' => $weeklyData,
                    'currency' => 'YER',
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getSummary: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load summary: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // Private Helper Methods
    // ============================================================

    /**
     * Get marketer statistics
     */
    private function getMarketerStats(int $marketerId): array
    {
        $totalInstitutions = Institution::where('created_by_marketer', $marketerId)->count();
        $activeInstitutions = Institution::where('created_by_marketer', $marketerId)
            ->where('status', 'active')
            ->count();
        $expiredInstitutions = Institution::where('created_by_marketer', $marketerId)
            ->where('status', 'expired')
            ->count();
        $pendingInstitutions = Institution::where('created_by_marketer', $marketerId)
            ->where('status', 'pending')
            ->count();

        $monthlyNew = Institution::where('created_by_marketer', $marketerId)
            ->whereMonth('created_at', now()->month)
            ->count();

        $totalCommissions = Commission::where('user_id', $marketerId)
            ->where('role', 'institution_marketer')
            ->where('status', 'paid')
            ->sum('amount');

        $pendingCommissions = Commission::where('user_id', $marketerId)
            ->where('role', 'institution_marketer')
            ->where('status', 'pending')
            ->sum('amount');

        $paidCommissions = Commission::where('user_id', $marketerId)
            ->where('role', 'institution_marketer')
            ->where('status', 'paid')
            ->sum('amount');

        $totalRevenue = RevenueTransaction::where('marketer_id', $marketerId)
            ->where('status', 'completed')
            ->sum('net_amount');

        return [
            'total_institutions' => $totalInstitutions,
            'active_institutions' => $activeInstitutions,
            'expired_institutions' => $expiredInstitutions,
            'pending_institutions' => $pendingInstitutions,
            'monthly_new' => $monthlyNew,
            'total_commissions' => (float) $totalCommissions,
            'pending_commissions' => (float) $pendingCommissions,
            'paid_commissions' => (float) $paidCommissions,
            'total_revenue' => (float) $totalRevenue,
        ];
    }

    /**
     * Get weekly data for chart
     */
    private function getWeeklyData(int $marketerId): array
    {
        $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
        $weeklyData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Institution::where('created_by_marketer', $marketerId)
                ->whereDate('created_at', $date->toDateString())
                ->count();

            $weeklyData[] = [
                'day' => $days[$date->dayOfWeek],
                'count' => $count,
                'date' => $date->toDateString(),
            ];
        }

        return $weeklyData;
    }

    /**
     * Calculate institutions growth percentage
     */
    private function calculateInstitutionsGrowth(int $marketerId): float
    {
        $currentMonth = Institution::where('created_by_marketer', $marketerId)
            ->whereMonth('created_at', now()->month)
            ->count();

        $previousMonth = Institution::where('created_by_marketer', $marketerId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        if ($previousMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
    }

    /**
     * Calculate active growth percentage
     */
    private function calculateActiveGrowth(int $marketerId): float
    {
        $currentMonth = Institution::where('created_by_marketer', $marketerId)
            ->where('status', 'active')
            ->whereMonth('updated_at', now()->month)
            ->count();

        $previousMonth = Institution::where('created_by_marketer', $marketerId)
            ->where('status', 'active')
            ->whereMonth('updated_at', now()->subMonth()->month)
            ->count();

        if ($previousMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
    }

    /**
     * Calculate monthly growth percentage
     */
    private function calculateMonthlyGrowth(int $marketerId): float
    {
        $currentMonth = Institution::where('created_by_marketer', $marketerId)
            ->whereMonth('created_at', now()->month)
            ->count();

        $previousMonth = Institution::where('created_by_marketer', $marketerId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        if ($previousMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
    }
}