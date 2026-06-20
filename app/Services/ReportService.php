<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Institution;
use App\Models\DiscountTransaction;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getRevenueReport(?string $period = 'monthly', ?string $startDate = null, ?string $endDate = null): array
    {
        $query = DiscountTransaction::query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        } elseif ($period) {
            $query->whereBetween('transaction_date', $this->getDateRange($period));
        }
        
        return [
            'total_transactions' => $query->count(),
            'total_savings' => (float) $query->sum('amount_saved'),
            'average_savings' => (float) $query->avg('amount_saved'),
            'total_discount_percentage' => (float) $query->avg('discount_percentage'),
            'transactions_by_day' => $this->getTransactionsByDay($query),
            'savings_by_month' => $this->getSavingsByMonth($query)
        ];
    }

    public function getCustomersReport(array $filters = []): array
    {
        $query = Customer::query()->with('user');
        
        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->active();
            } elseif ($filters['status'] === 'expired') {
                $query->expired();
            }
        }
        
        if (isset($filters['marketer_id'])) {
            $query->where('created_by_marketer', $filters['marketer_id']);
        }
        
        return [
            'total_customers' => $query->count(),
            'active_customers' => $query->active()->count(),
            'expired_customers' => $query->expired()->count(),
            'expiring_soon' => $query->expiringSoon()->count(),
            'total_savings' => (float) $query->sum('total_discount_saved'),
            'average_savings_per_customer' => (float) $query->avg('total_discount_saved'),
            'customers_by_month' => $this->getCustomersByMonth($query)
        ];
    }

    public function getInstitutionsReport(array $filters = []): array
    {
        $query = Institution::query();
        
        if (isset($filters['type_id'])) {
            $query->where('type_id', $filters['type_id']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['marketer_id'])) {
            $query->where('created_by_marketer', $filters['marketer_id']);
        }
        
        return [
            'total_institutions' => $query->count(),
            'active_institutions' => $query->active()->count(),
            'inactive_institutions' => $query->where('status', 'inactive')->count(),
            'expired_institutions' => $query->where('status', 'expired')->count(),
            'expiring_soon' => $query->expiringSoon()->count(),
            'total_discounts_given' => $query->withCount('discountTransactions')->get()->sum('discount_transactions_count'),
            'total_savings_given' => (float) $query->withSum('discountTransactions', 'amount_saved')->get()->sum('discount_transactions_sum_amount_saved'),
            'institutions_by_type' => $this->getInstitutionsByType($query)
        ];
    }

    public function getCommissionsReport(array $filters = []): array
    {
        $query = Commission::query();
        
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        return [
            'total_commissions' => (float) $query->sum('amount'),
            'pending_commissions' => (float) $query->where('status', 'pending')->sum('amount'),
            'paid_commissions' => (float) $query->where('status', 'paid')->sum('amount'),
            'average_commission' => (float) $query->avg('amount'),
            'commissions_by_marketer' => $this->getCommissionsByMarketer($query)
        ];
    }

    public function getDashboardStats(): array
    {
        return [
            'total_customers' => Customer::count(),
            'total_institutions' => Institution::count(),
            'total_transactions_today' => DiscountTransaction::whereDate('transaction_date', today())->count(),
            'total_savings_today' => (float) DiscountTransaction::whereDate('transaction_date', today())->sum('amount_saved'),
            'pending_commissions' => (float) Commission::where('status', 'pending')->sum('amount'),
            'active_institutions' => Institution::active()->count(),
            'expiring_memberships' => Customer::expiringSoon(30)->count(),
            'recent_customers' => Customer::with('user')->latest()->limit(10)->get(),
            'recent_institutions' => Institution::with('type')->latest()->limit(10)->get(),
            'recent_transactions' => DiscountTransaction::with(['customer', 'institution'])->latest()->limit(10)->get()
        ];
    }

    public function getTopInstitutions(int $limit = 10, string $period = 'monthly'): array
    {
        $query = DiscountTransaction::whereBetween('transaction_date', $this->getDateRange($period))
            ->select('institution_id', DB::raw('COUNT(*) as transactions_count'), DB::raw('SUM(amount_saved) as total_savings'))
            ->groupBy('institution_id')
            ->orderBy('total_savings', 'desc')
            ->limit($limit);
        
        $results = $query->get();
        
        return $results->map(function ($item) {
            $institution = Institution::find($item->institution_id);
            return [
                'id' => $institution->id,
                'name' => $institution->name,
                'transactions_count' => $item->transactions_count,
                'total_savings' => (float) $item->total_savings
            ];
        })->toArray();
    }

    public function getTopMarketers(int $limit = 10, ?string $role = null): array
    {
        $query = Commission::where('status', 'paid');
        
        if ($role) {
            $query->where('role', $role);
        }
        
        $results = $query->select('user_id', DB::raw('SUM(amount) as total_commissions'))
            ->groupBy('user_id')
            ->orderBy('total_commissions', 'desc')
            ->limit($limit)
            ->get();
        
        return $results->map(function ($item) {
            $user = User::find($item->user_id);
            return [
                'id' => $user->id,
                'name' => $user->full_name,
                'role' => $user->role,
                'total_commissions' => (float) $item->total_commissions
            ];
        })->toArray();
    }

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

    protected function getTransactionsByDay($query): array
    {
        return $query->select(DB::raw('DATE(transaction_date) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get()
            ->toArray();
    }

    protected function getSavingsByMonth($query): array
    {
        return $query->select(DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as month'), DB::raw('SUM(amount_saved) as total'))
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->toArray();
    }

    protected function getCustomersByMonth($query): array
    {
        return $query->select(DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'), DB::raw('COUNT(*) as count'))
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->toArray();
    }

    protected function getInstitutionsByType($query): array
    {
        return $query->select('type_id', DB::raw('COUNT(*) as count'))
            ->groupBy('type_id')
            ->with('type')
            ->get()
            ->map(function ($item) {
                return [
                    'type_name' => $item->type->name_ar ?? $item->type->name,
                    'count' => $item->count
                ];
            })
            ->toArray();
    }

    protected function getCommissionsByMarketer($query): array
    {
        return $query->select('user_id', DB::raw('SUM(amount) as total'))
            ->groupBy('user_id')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->with('user')
            ->get()
            ->map(function ($item) {
                return [
                    'marketer_name' => $item->user->full_name,
                    'total_commissions' => (float) $item->total
                ];
            })
            ->toArray();
    }
}