<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function all(array $filters = [], int $perPage = 15)
    {
        $query = $this->model->query();
        
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('full_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        return $query->paginate($perPage);
    }

    public function find($id)
    {
        return $this->model->findOrFail($id);
    }

    public function findByPhone(string $phone): ?User
    {
        return $this->model->where('phone', $phone)->first();
    }

    public function create(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        return $this->model->create($data);
    }

    public function update($id, array $data)
    {
        $user = $this->find($id);
        
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        $user->update($data);
        return $user;
    }

    public function delete($id)
    {
        $user = $this->find($id);
        return $user->delete();
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
        return $this->model->paginate($perPage);
    }

    public function count(): int
    {
        return $this->model->count();
    }

    public function updatePassword(int $userId, string $password): bool
    {
        $user = $this->find($userId);
        $user->password = Hash::make($password);
        return $user->save();
    }

    public function findByRole(string $role, int $perPage = 15)
    {
        return $this->model->where('role', $role)->paginate($perPage);
    }

    public function findActiveUsers(int $perPage = 15)
    {
        return $this->model->where('status', 'active')->paginate($perPage);
    }

    public function getMarketersByType(string $type)
    {
        return $this->model->where('role', $type)->get();
    }

    public function getTopMarketers(int $limit = 10, ?string $role = null)
    {
        $query = $this->model->query();
        
        if ($role) {
            $query->where('role', $role);
        }
        
        return $query->limit($limit)->get();
    }

    public function getUserWithDetails(int $userId): ?User
    {
        return $this->model->with(['customer', 'institutionsOwned'])->find($userId);
    }

    public function updateStatus(int $userId, string $status): bool
    {
        $user = $this->find($userId);
        $user->status = $status;
        return $user->save();
    }

    public function getUsersByStatus(string $status, int $perPage = 15)
    {
        return $this->model->where('status', $status)->paginate($perPage);
    }

    public function searchUsers(string $searchTerm, int $perPage = 15)
    {
        return $this->model->where('full_name', 'like', '%' . $searchTerm . '%')
            ->orWhere('phone', 'like', '%' . $searchTerm . '%')
            ->paginate($perPage);
    }

    public function getCustomersList()
    {
        return $this->model->where('role', 'customer')->get(['id', 'full_name', 'phone']);
    }

    public function getMarketersList()
    {
        return $this->model->whereIn('role', ['customer_marketer', 'institution_marketer'])
            ->get(['id', 'full_name', 'phone', 'role']);
    }

    public function getInstitutionOwnersList()
    {
        return $this->model->where('role', 'institution_owner')->get(['id', 'full_name', 'phone']);
    }
}