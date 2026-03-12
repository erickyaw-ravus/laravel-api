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
     * Update an existing user. Only Super Admin can change role; others can update profile fields only.
     */
    public function update(User $user, array $data, User $updatedBy): User
    {
        if (array_key_exists('role', $data)) {
            if ($updatedBy->hasRole('Super Admin')) {
                $user->syncRoles([$data['role']]);
            }
            unset($data['role']);
        }

        $user->fill($data);
        $user->save();

        Log::info('User updated', [
            'user_id' => $user->id,
            'updated_by' => $updatedBy->id,
        ]);

        return $user;
    }
}
