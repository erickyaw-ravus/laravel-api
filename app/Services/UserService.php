<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class UserService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * List users with pagination.
     */
    public function list(int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $perPage = min(max($perPage, 1), self::MAX_PER_PAGE);

        return User::query()->orderBy('id')->paginate($perPage);
    }

    /**
     * Create a new user and assign default role.
     */
    public function store(array $data, User $createdBy): User
    {
        $user = User::create($data);
        $user->assignRole('User');

        Log::info('User created', [
            'user_id' => $user->id,
            'created_by' => $createdBy->id,
        ]);

        return $user;
    }

    /**
     * Update an existing user without changing the role.
     */
    public function update(User $user, array $data, User $updatedBy): User
    {
        $user->fill($data);
        $user->save();

        Log::info('User updated', [
            'user_id' => $user->id,
            'updated_by' => $updatedBy->id,
        ]);

        return $user;
    }

    /**
     * Update a user's roles. Replaces all existing roles with the given set.
     * Only Super Admin should be able to call this (enforced by permission middleware).
     *
     * @param  string[]  $roles  Role names to assign
     */
    public function updateRole(User $user, array $roles, User $updatedBy): User
    {
        $user->syncRoles($roles);

        Log::info('User roles updated', [
            'user_id' => $user->id,
            'updated_by' => $updatedBy->id,
            'roles' => $roles,
        ]);

        return $user;
    }
}
