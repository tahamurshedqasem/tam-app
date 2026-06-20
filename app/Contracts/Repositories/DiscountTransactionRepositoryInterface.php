<?php

namespace App\Contracts\Repositories;

use App\Models\DiscountTransaction;

interface DiscountTransactionRepositoryInterface extends RepositoryInterface
{
    public function findByCustomer(int $customerId, int $perPage = 15);
    public function findByInstitution(int $institutionId, int $perPage = 15);
    public function findByOwner(int $ownerId, int $perPage = 15);
    public function findByDateRange(string $startDate, string $endDate, int $perPage = 15);
    public function getTodayTransactions();
    public function getTransactionsByPeriod(string $period); // daily, weekly, monthly, yearly
    public function getTransactionWithDetails(int $transactionId): ?DiscountTransaction;
    public function getCustomerTotalSavings(int $customerId): float;
    public function getInstitutionTotalSavings(int $institutionId): float;
    public function getTotalSavingsByDateRange(string $startDate, string $endDate): float;
    public function getTransactionsCountByDay(int $days = 30);
    public function getSavingsByMonth(int $months = 12);
    public function getTopCustomersBySavings(int $limit = 10, string $startDate = null, string $endDate = null);
    public function getTopInstitutionsByTransactions(int $limit = 10, string $startDate = null, string $endDate = null);
    public function getAverageDiscountPercentage();
    public function getTransactionsByVerificationMethod(string $method);
}