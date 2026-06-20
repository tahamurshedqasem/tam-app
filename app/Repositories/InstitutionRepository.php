<?php

namespace App\Repositories;

use App\Contracts\Repositories\InstitutionRepositoryInterface;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;

class InstitutionRepository implements InstitutionRepositoryInterface
{
    protected $model;

    public function __construct(Institution $model)
    {
        $this->model = $model;
    }

    public function all(array $filters = [], int $perPage = 15)
    {
        $query = $this->model->with('type');
        
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
        }
        
        if (isset($filters['type_id'])) {
            $query->where('type_id', $filters['type_id']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['city'])) {
            $query->where('address', 'like', '%' . $filters['city'] . '%');
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function find($id)
    {
        return $this->model->with(['type', 'owner', 'owners'])->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update($id, array $data)
    {
        $institution = $this->find($id);
        $institution->update($data);
        return $institution;
    }

    public function delete($id)
    {
        $institution = $this->find($id);
        return $institution->delete();
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
        return $this->model->with('type')->paginate($perPage);
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function findByPhone(string $phone): ?Institution
    {
        return $this->model->where('phone', $phone)->first();
    }

    public function findByEmail(string $email): ?Institution
    {
        return $this->model->where('email', $email)->first();
    }

    public function findByType(int $typeId, int $perPage = 15)
    {
        return $this->model->with('type')
            ->where('type_id', $typeId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findByOwner(int $ownerId, int $perPage = 15)
    {
        return $this->model->with('type')
            ->where('owner_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findByMarketer(int $marketerId, int $perPage = 15)
    {
        return $this->model->with('type')
            ->where('created_by_marketer', $marketerId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findActiveInstitutions(int $perPage = 15)
    {
        return $this->model->with('type')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function findExpiredAgreements(int $perPage = 15)
    {
        return $this->model->with('type')
            ->where('status', 'expired')
            ->orderBy('agreement_expiry_date', 'desc')
            ->paginate($perPage);
    }

    public function findExpiringSoon(int $days = 30, int $perPage = 15)
    {
        return $this->model->with('type')
            ->where('agreement_expiry_date', '<=', now()->addDays($days))
            ->where('agreement_expiry_date', '>', now())
            ->orderBy('agreement_expiry_date', 'asc')
            ->paginate($perPage);
    }

    public function getNearby(float $latitude, float $longitude, float $distance = 10)
    {
        return $this->model->selectRaw("*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) as distance", [$latitude, $longitude, $latitude])
            ->having('distance', '<', $distance)
            ->orderBy('distance')
            ->get();
    }

    public function getInstitutionWithDetails(int $institutionId): ?Institution
    {
        return $this->model->with(['type', 'owner', 'owners', 'discountTransactions'])
            ->find($institutionId);
    }

    public function getInstitutionWithOwners(int $institutionId): ?Institution
    {
        return $this->model->with('owners')->find($institutionId);
    }

    public function updateDiscountPercentage(int $institutionId, float $percentage): bool
    {
        $institution = $this->find($institutionId);
        $institution->discount_percentage = $percentage;
        return $institution->save();
    }

    public function updateStatus(int $institutionId, string $status): bool
    {
        $institution = $this->find($institutionId);
        $institution->status = $status;
        return $institution->save();
    }

    public function renewAgreement(int $institutionId, string $newExpiryDate): bool
    {
        $institution = $this->find($institutionId);
        $institution->agreement_expiry_date = $newExpiryDate;
        return $institution->save();
    }

    public function searchInstitutions(string $searchTerm, int $perPage = 15)
    {
        return $this->model->with('type')
            ->where('name', 'like', '%' . $searchTerm . '%')
            ->orWhere('phone', 'like', '%' . $searchTerm . '%')
            ->orWhere('address', 'like', '%' . $searchTerm . '%')
            ->paginate($perPage);
    }

    public function getInstitutionsByStatus(string $status, int $perPage = 15)
    {
        return $this->model->with('type')
            ->where('status', $status)
            ->paginate($perPage);
    }

    public function getTopInstitutions(int $limit = 10, string $period = 'monthly')
    {
        $dateRange = match($period) {
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            'yearly' => now()->subYear(),
            default => now()->subMonth(),
        };
        
        return $this->model->withCount(['discountTransactions' => function($query) use ($dateRange) {
                $query->where('created_at', '>=', $dateRange);
            }])
            ->withSum(['discountTransactions' => function($query) use ($dateRange) {
                $query->where('created_at', '>=', $dateRange);
            }], 'amount_saved')
            ->orderBy('discount_transactions_count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getInstitutionsCountByType()
    {
        return $this->model->select('type_id', DB::raw('count(*) as total'))
            ->groupBy('type_id')
            ->with('type')
            ->get();
    }

    public function addOwner(int $institutionId, int $userId, bool $isPrimary = false): bool
    {
        $institution = $this->find($institutionId);
        $institution->owners()->attach($userId, ['is_primary' => $isPrimary]);
        
        if ($isPrimary) {
            $institution->owner_id = $userId;
            $institution->save();
        }
        
        return true;
    }

    public function removeOwner(int $institutionId, int $userId): bool
    {
        $institution = $this->find($institutionId);
        $institution->owners()->detach($userId);
        
        if ($institution->owner_id === $userId) {
            $newPrimary = $institution->owners()->wherePivot('is_primary', true)->first();
            $institution->owner_id = $newPrimary ? $newPrimary->id : null;
            $institution->save();
        }
        
        return true;
    }

    public function getPrimaryOwner(int $institutionId): ?int
    {
        $institution = $this->find($institutionId);
        $primaryOwner = $institution->owners()->wherePivot('is_primary', true)->first();
        return $primaryOwner ? $primaryOwner->id : $institution->owner_id;
    }
}