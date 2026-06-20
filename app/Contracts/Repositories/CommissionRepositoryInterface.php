<?php

namespace App\Contracts\Repositories;

use App\Models\Commission;

interface CommissionRepositoryInterface extends RepositoryInterface
{
    public function findByUser(int $userId, int $perPage = 15);
    public function findByRole(string $role, int $perPage = 15);
    public function findByStatus(string $status, int $perPage = 15);
    public function findByTransaction(int $transactionId): ?Commission;
    public function getPendingCommissions(int $perPage = 15);
    public function getPaidCommissions(int $perPage = 15);
    public function getUserTotalCommissions(int $userId): float;
    public function getUserPendingCommissions(int $userId): float;
    public function getUserPaidCommissions(int $userId): float;
    public function getTotalPendingCommissions(): float;
    public function getTotalPaidCommissions(): float;
    public function markAsPaid(int $commissionId, string $paidAt = null): bool;
    public function cancelCommission(int $commissionId): bool;
    public function getCommissionsByDateRange(string $startDate, string $endDate);
    public function getCommissionsByPeriod(string $period);
    public function getTopMarketersByCommissions(int $limit = 10, string $role = null);
    public function getCommissionsSummary();
}