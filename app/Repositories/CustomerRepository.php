<?php

namespace App\Repositories;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerRepository implements CustomerRepositoryInterface
{
    protected $model;

    public function __construct(Customer $model)
    {
        $this->model = $model;
    }

    public function all(array $filters = [], int $perPage = 15)
    {
        $query = $this->model->with('user');
        
        if (isset($filters['search'])) {
            $query->whereHas('user', function($q) use ($filters) {
                $q->where('full_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        if (isset($filters['status']) && $filters['status'] === 'active') {
            $query->active();
        }
        
        if (isset($filters['status']) && $filters['status'] === 'expired') {
            $query->expired();
        }
        
        if (isset($filters['membership_status'])) {
            if ($filters['membership_status'] === 'active') {
                $query->active();
            } elseif ($filters['membership_status'] === 'expired') {
                $query->expired();
            } elseif ($filters['membership_status'] === 'expiring_soon') {
                $query->expiringSoon();
            }
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function find($id)
    {
        return $this->model->with('user')->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update($id, array $data)
    {
        $customer = $this->find($id);
        $customer->update($data);
        return $customer;
    }

    public function delete($id)
    {
        $customer = $this->find($id);
        return $customer->delete();
    }

    public function findWhere(array $conditions)
    {
        $query = $this->model->query();
        
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }
        
        return $query->get();
    }

    public function paginate(int $perPage = 15)
    {
        return $this->model->with('user')->paginate($perPage);
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function findByMembershipNumber(string $membershipNumber): ?Customer
    {
        return $this->model->with('user')->where('membership_number', $membershipNumber)->first();
    }

    public function findByUserId(int $userId): ?Customer
    {
        return $this->model->with('user')->where('user_id', $userId)->first();
    }

    public function findByMarketer(int $marketerId, int $perPage = 15)
    {
        return $this->model->with('user')
            ->where('created_by_marketer', $marketerId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findActiveCustomers(int $perPage = 15)
    {
        return $this->model->with('user')
            ->active()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findExpiredCustomers(int $perPage = 15)
    {
        return $this->model->with('user')
            ->expired()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findExpiringSoon(int $days = 30, int $perPage = 15)
    {
        return $this->model->with('user')
            ->expiringSoon($days)
            ->orderBy('membership_expiry_date', 'asc')
            ->paginate($perPage);
    }

    public function getCustomerWithUser(int $customerId): ?Customer
    {
        return $this->model->with('user')->find($customerId);
    }

    public function getCustomerWithTransactions(int $customerId): ?Customer
    {
        return $this->model->with(['user', 'discountTransactions'])->find($customerId);
    }

    public function updateMembershipExpiry(int $customerId, string $expiryDate): bool
    {
        $customer = $this->find($customerId);
        $customer->membership_expiry_date = $expiryDate;
        return $customer->save();
    }

    public function updateTotalSavings(int $customerId, float $amount): bool
    {
        $customer = $this->find($customerId);
        $customer->total_discount_saved += $amount;
        return $customer->save();
    }

    public function searchCustomers(string $searchTerm, int $perPage = 15)
    {
        return $this->model->whereHas('user', function($q) use ($searchTerm) {
            $q->where('full_name', 'like', '%' . $searchTerm . '%')
              ->orWhere('phone', 'like', '%' . $searchTerm . '%');
        })->orWhere('membership_number', 'like', '%' . $searchTerm . '%')
        ->paginate($perPage);
    }

    public function getCustomersByStatus(string $status, int $perPage = 15)
    {
        if ($status === 'active') {
            return $this->findActiveCustomers($perPage);
        } elseif ($status === 'expired') {
            return $this->findExpiredCustomers($perPage);
        }
        
        return $this->all([], $perPage);
    }

    public function getTopCustomers(int $limit = 10)
    {
        return $this->model->with('user')
            ->orderBy('total_discount_saved', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getCustomersByDateRange(string $startDate, string $endDate)
    {
        return $this->model->with('user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
    }

    public function getCustomersCountByMonth(int $months = 12)
    {
        $result = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = $this->model->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
            
            $result[] = [
                'month' => $month->format('Y-m'),
                'year' => $month->year,
                'month_name' => $month->format('F'),
                'count' => $count
            ];
        }
        
        return $result;
    }
}