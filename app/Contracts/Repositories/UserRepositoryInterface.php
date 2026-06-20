<?php

namespace App\Contracts\Repositories;

use App\Models\User;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByPhone(string $phone): ?User;
    public function findByRole(string $role, int $perPage = 15);
    public function findActiveUsers(int $perPage = 15);
    public function updatePassword(int $userId, string $password): bool;
    public function getMarketersByType(string $type); // customer_marketer or institution_marketer
    public function getTopMarketers(int $limit = 10, ?string $role = null);
    public function getUserWithDetails(int $userId): ?User;
    public function updateStatus(int $userId, string $status): bool;
    public function getUsersByStatus(string $status, int $perPage = 15);
    public function searchUsers(string $searchTerm, int $perPage = 15);
    public function getCustomersList();
    public function getMarketersList();
    public function getInstitutionOwnersList();
}