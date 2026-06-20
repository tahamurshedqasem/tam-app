<?php

namespace App\Contracts\Repositories;

use App\Models\Customer;

interface CustomerRepositoryInterface extends RepositoryInterface
{
    public function findByMembershipNumber(string $membershipNumber): ?Customer;
    public function findByUserId(int $userId): ?Customer;
    public function findByMarketer(int $marketerId, int $perPage = 15);
    public function findActiveCustomers(int $perPage = 15);
    public function findExpiredCustomers(int $perPage = 15);
    public function findExpiringSoon(int $days = 30, int $perPage = 15);
    public function getCustomerWithUser(int $customerId): ?Customer;
    public function getCustomerWithTransactions(int $customerId): ?Customer;
    public function updateMembershipExpiry(int $customerId, string $expiryDate): bool;
    public function updateTotalSavings(int $customerId, float $amount): bool;
    public function searchCustomers(string $searchTerm, int $perPage = 15);
    public function getCustomersByStatus(string $status, int $perPage = 15);
    public function getTopCustomers(int $limit = 10);
    public function getCustomersByDateRange(string $startDate, string $endDate);
    public function getCustomersCountByMonth(int $months = 12);
}