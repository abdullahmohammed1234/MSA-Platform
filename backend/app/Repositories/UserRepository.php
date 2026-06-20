<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    public function all(int $perPage = 15)
    {
        return User::paginate($perPage);
    }
}
