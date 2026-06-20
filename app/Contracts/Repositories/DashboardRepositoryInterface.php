<?php

namespace App\Contracts\Repositories;

interface DashboardRepositoryInterface
{
    public function getAdminStats(): array;
    public function getCustomerMarketerStats(int $marketerId): array;
    public function getInstitutionMarketerStats(int $marketerId): array;
    public function getInstitutionOwnerStats(int $ownerId): array;
    public function getCustomerStats(int $customerId): array;
    public function getChartData(string $type = 'transactions', int $months = 12): array;
    public function getRecentActivities(int $limit = 10): array;
    public function getAlerts(): array;
}