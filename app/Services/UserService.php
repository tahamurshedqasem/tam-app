<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getAllUsers(array $filters = [], int $perPage = 15)
    {
        return $this->userRepository->all($filters, $perPage);
    }

    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $data['password'] = $data['password'] ?? $this->generatePassword();
            return $this->userRepository->create($data);
        });
    }

    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $this->userRepository->update($user->id, $data);
            return $user->fresh();
        });
    }

    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            return $this->userRepository->delete($user->id);
        });
    }

    public function activateUser(User $user): User
    {
        $this->userRepository->update($user->id, ['status' => 'active']);
        return $user->fresh();
    }

    public function deactivateUser(User $user): User
    {
        $this->userRepository->update($user->id, ['status' => 'inactive']);
        return $user->fresh();
    }

    protected function generatePassword(): string
    {
        return \Illuminate\Support\Str::random(8);
    }
}