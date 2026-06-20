<?php

namespace App\Services;

use App\Models\User;
use App\Models\Customer;
use App\Models\Institution;
use App\Models\DiscountTransaction;
use App\Models\Commission;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getAdminDashboard(): array
    {
        return [
            'stats' => [
                'total_customers' => Customer::count(),
                'total_institutions' => Institution::count(),
                'total_transactions' => DiscountTransaction::count(),
                'total_savings' => (float) DiscountTransaction::sum('amount_saved'),
                'total_commissions_pending' => (float) Commission::where('status', 'pending')->sum('amount'),
                'total_commissions_paid' => (float) Commission::where('status', 'paid')->sum('amount'),
                'active_institutions' => Institution::where('status', 'active')->count(),
                'active_customers' => Customer::active()->count(),
            ],
            'charts' => $this->getChartData(),
            'recent' => [
                'customers' => Customer::with('user')->latest()->limit(10)->get(),
                'institutions' => Institution::with('type')->latest()->limit(10)->get(),
                'transactions' => DiscountTransaction::with(['customer', 'institution'])->latest()->limit(10)->get(),
            ],
            'alerts' => [
                'expiring_memberships' => Customer::expiringSoon(30)->count(),
                'expiring_agreements' => Institution::expiringSoon(30)->count(),
                'pending_commissions' => Commission::where('status', 'pending')->count(),
            ]
        ];
    }

    public function getMarketerDashboard(User $marketer): array
    {
        $data = [];
        
        if ($marketer->isCustomerMarketer()) {
            $customers = $marketer->createdCustomers;
            $data = [
                'stats' => [
                    'total_customers' => $customers->count(),
                    'active_customers' => $customers->filter(fn($c) => $c->isValidMembership())->count(),
                    'total_savings' => (float) $customers->sum('total_discount_saved'),
                    'total_commissions' => (float) $marketer->commissions()->where('status', 'pending')->sum('amount'),
                    'paid_commissions' => (float) $marketer->commissions()->where('status', 'paid')->sum('amount'),
                ],
                'recent_customers' => $customers->take(10),
                'commissions' => $marketer->commissions()->latest()->limit(10)->get(),
            ];
        }
        
        if ($marketer->isInstitutionMarketer()) {
            $institutions = $marketer->createdInstitutions;
            $data = [
                'stats' => [
                    'total_institutions' => $institutions->count(),
                    'active_institutions' => $institutions->where('status', 'active')->count(),
                    'total_transactions' => $institutions->sum(fn($i) => $i->discountTransactions->count()),
                    'total_savings' => (float) $institutions->sum(fn($i) => $i->discountTransactions->sum('amount_saved')),
                    'total_commissions' => (float) $marketer->commissions()->where('status', 'pending')->sum('amount'),
                    'paid_commissions' => (float) $marketer->commissions()->where('status', 'paid')->sum('amount'),
                ],
                'recent_institutions' => $institutions->take(10),
                'commissions' => $marketer->commissions()->latest()->limit(10)->get(),
            ];
        }
        
        return $data;
    }

    public function getOwnerDashboard(User $owner): array
    {
        $institutions = $owner->institutionsOwned;
        
        return [
            'stats' => [
                'total_institutions' => $institutions->count(),
                'total_transactions' => $institutions->sum(fn($i) => $i->discountTransactions->count()),
                'total_savings_given' => (float) $institutions->sum(fn($i) => $i->discountTransactions->sum('amount_saved')),
                'today_transactions' => $institutions->sum(fn($i) => $i->discountTransactions->whereDate('created_at', today())->count()),
            ],
            'recent_transactions' => DiscountTransaction::whereIn('institution_id', $institutions->pluck('id'))
                ->with(['customer', 'institution'])
                ->latest()
                ->limit(10)
                ->get(),
            'institutions' => $institutions,
        ];
    }

    public function getCustomerDashboard(User $user): array
    {
        $customer = $user->customer;
        
        return [
            'membership' => [
                'number' => $customer->membership_number,
                'status' => $customer->membership_status,
                'expiry_date' => $customer->membership_expiry_date,
                'days_remaining' => $customer->days_remaining,
            ],
            'stats' => [
                'total_savings' => (float) $customer->total_discount_saved,
                'total_transactions' => $customer->discountTransactions->count(),
                'total_discounts' => $customer->discountTransactions->count(),
            ],
            'recent_transactions' => $customer->discountTransactions()
                ->with('institution')
                ->latest()
                ->limit(10)
                ->get(),
            'nearby_institutions' => $this->getNearbyInstitutionsForCustomer($customer),
        ];
    }

    protected function getChartData(): array
    {
        $last12Months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $last12Months->push($month->format('Y-m'));
        }
        
        $transactions = DiscountTransaction::select(
            DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as month'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount_saved) as savings')
        )
        ->where('transaction_date', '>=', now()->subMonths(11)->startOfMonth())
        ->groupBy('month')
        ->get()
        ->keyBy('month');
        
        $commissions = Commission::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(amount) as total')
        )
        ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
        ->groupBy('month')
        ->get()
        ->keyBy('month');
        
        return [
            'transactions' => $last12Months->map(function ($month) use ($transactions) {
                return $transactions[$month]->count ?? 0;
            })->toArray(),
            'savings' => $last12Months->map(function ($month) use ($transactions) {
                return (float) ($transactions[$month]->savings ?? 0);
            })->toArray(),
            'commissions' => $last12Months->map(function ($month) use ($commissions) {
                return (float) ($commissions[$month]->total ?? 0);
            })->toArray(),
            'months' => $last12Months->map(function ($month) {
                return date('M Y', strtotime($month . '-01'));
            })->toArray(),
        ];
    }

    protected function getNearbyInstitutionsForCustomer($customer): array
    {
        // يمكن تنفيذ منطق جلب المؤسسات القريبة بناءً على موقع العميل
        return Institution::active()
            ->limit(5)
            ->get()
            ->toArray();
    }
}