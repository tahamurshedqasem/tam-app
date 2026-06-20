<?php

namespace App\Contracts\Repositories;

use App\Models\Notification;

interface NotificationRepositoryInterface extends RepositoryInterface
{
    public function findByUser(int $userId, int $perPage = 15);
    public function findUnreadByUser(int $userId, int $perPage = 15);
    public function findReadByUser(int $userId, int $perPage = 15);
    public function findByType(string $type, int $perPage = 15);
    public function markAsRead(int $notificationId): bool;
    public function markAllAsRead(int $userId): int;
    public function deleteOldNotifications(int $days = 30): int;
    public function getUserUnreadCount(int $userId): int;
    public function createForUser(int $userId, string $title, string $body, string $type = 'info', ?array $data = null): Notification;
    public function createForRole(string $role, string $title, string $body, string $type = 'info', ?array $data = null);
    public function createForAll(string $title, string $body, string $type = 'info', ?array $data = null);
}