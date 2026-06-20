<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CommissionController extends Controller
{
    protected CommissionService $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
        // $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/commissions
     * قائمة العمولات
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['user_id', 'role', 'status', 'from_date', 'to_date']);
            $perPage = $request->get('per_page', 15);

            $commissions = $this->commissionService->getCommissions($filters, $perPage);

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
            Log::error('Error in index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/commissions/{id}
     * عرض تفاصيل عمولة
     */
    public function show($id)
    {
        try {
            $commission = Commission::with(['user', 'customer', 'institution'])->findOrFail($id);
            $details = $this->commissionService->getCommissionDetails($commission);

            return response()->json([
                'success' => true,
                'data' => $details
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
     * PUT /api/commissions/{id}/pay
     * دفع عمولة
     */
    public function pay($id)
    {
        try {
            $commission = Commission::findOrFail($id);
            
            if ($commission->isPaid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'العمولة مدفوعة بالفعل'
                ], 422);
            }

            $result = $this->commissionService->payCommission($commission);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم دفع العمولة بنجاح',
                    'data' => $commission->fresh()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'فشل في دفع العمولة'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error in pay: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/commissions/stats
     * إحصائيات العمولات
     */
    public function stats(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            
            if ($userId) {
                $user = \App\Models\User::findOrFail($userId);
                $stats = $this->commissionService->getMarketerStats($user);
            } else {
                $stats = $this->commissionService->getRevenueStats();
            }

            return response()->json([
                'success' => true,
                'data' => $stats
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
     * GET /api/commissions/revenue-transactions
     * قائمة معاملات الإيرادات
     */
    public function revenueTransactions(Request $request)
    {
        try {
            $filters = $request->only(['type', 'status', 'from_date', 'to_date']);
            $perPage = $request->get('per_page', 15);

            $transactions = $this->commissionService->getRevenueTransactions($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions->items(),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in revenueTransactions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/commissions/monthly-report
     * تقرير شهري
     */
    public function monthlyReport(Request $request)
    {
        try {
            $year = $request->get('year', now()->year);
            $month = $request->get('month', now()->month);

            $report = $this->commissionService->getMonthlyReport($year, $month);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            Log::error('Error in monthlyReport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/commissions/marketer/{id}/stats
     * إحصائيات مسوق معين
     */
    public function marketerStats($id)
    {
        try {
            $user = \App\Models\User::findOrFail($id);
            $stats = $this->commissionService->getMarketerStats($user);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error in marketerStats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/commissions/marketers/top
     * أفضل المسوقين من حيث العمولات
     */
    public function topMarketers(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            
            $marketers = \App\Models\User::whereIn('role', ['customer_marketer', 'institution_marketer'])
                ->withSum('commissions as total_commission', 'amount')
                ->withCount(['commissions as paid_commissions_count' => function ($query) {
                    $query->where('status', 'paid');
                }])
                ->orderBy('total_commission', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $marketers->map(function ($marketer) {
                    return [
                        'id' => $marketer->id,
                        'name' => $marketer->full_name,
                        'role' => $marketer->role,
                        'total_commission' => round($marketer->total_commission ?? 0, 2),
                        'paid_commissions' => $marketer->paid_commissions_count ?? 0,
                        'customers_count' => $marketer->customers_count ?? 0,
                        'institutions_count' => $marketer->institutions_count ?? 0,
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Error in topMarketers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}