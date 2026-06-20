<?php

namespace App\Contracts\Repositories;

use App\Models\Institution;

interface InstitutionRepositoryInterface extends RepositoryInterface
{
    public function findByPhone(string $phone): ?Institution;
    public function findByEmail(string $email): ?Institution;
    public function findByType(int $typeId, int $perPage = 15);
    public function findByOwner(int $ownerId, int $perPage = 15);
    public function findByMarketer(int $marketerId, int $perPage = 15);
    public function findActiveInstitutions(int $perPage = 15);
    public function findExpiredAgreements(int $perPage = 15);
    public function findExpiringSoon(int $days = 30, int $perPage = 15);
    public function getNearby(float $latitude, float $longitude, float $distance = 10);
    public function getInstitutionWithDetails(int $institutionId): ?Institution;
    public function getInstitutionWithOwners(int $institutionId): ?Institution;
    public function updateDiscountPercentage(int $institutionId, float $percentage): bool;
    public function updateStatus(int $institutionId, string $status): bool;
    public function renewAgreement(int $institutionId, string $newExpiryDate): bool;
    public function searchInstitutions(string $searchTerm, int $perPage = 15);
    public function getInstitutionsByStatus(string $status, int $perPage = 15);
    public function getTopInstitutions(int $limit = 10, string $period = 'monthly');
    public function getInstitutionsCountByType();
    public function addOwner(int $institutionId, int $userId, bool $isPrimary = false): bool;
    public function removeOwner(int $institutionId, int $userId): bool;
    public function getPrimaryOwner(int $institutionId): ?int;
}