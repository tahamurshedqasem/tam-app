<?php

namespace App\Contracts\Repositories;

use App\Models\InstitutionType;

interface InstitutionTypeRepositoryInterface extends RepositoryInterface
{
    public function findByName(string $name): ?InstitutionType;
    public function findActiveTypes();
    public function getTypeWithInstitutionsCount(int $typeId): ?InstitutionType;
    public function updateStatus(int $typeId, bool $isActive): bool;
    public function getTypesWithStats();
}