<?php

namespace App\Contracts\Repositories;

use App\Models\ActivityLog;

interface ActivityLogRepositoryInterface extends RepositoryInterface
{
    public function findByUser(int $userId, int $perPage = 15);
    public function findByModule(string $module, int $perPage = 15);
    public function findByAction(string $action, int $perPage = 15);
    public function findByDateRange(string $startDate, string $endDate, int $perPage = 15);
    public function logActivity(int $userId, string $action, string $module, string $description, ?array $oldData = null, ?array $newData = null): ActivityLog;
    public function getUserActivities(int $userId, int $limit = 50);
    public function getModuleActivities(string $module, int $limit = 50);
    public function getRecentActivities(int $limit = 100);
    public function deleteOldLogs(int $days = 90): int;
    public function getActivitiesSummary();
}