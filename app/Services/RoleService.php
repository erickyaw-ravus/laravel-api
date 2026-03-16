<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RoleService
{
    /**
     * List all roles (no pagination).
     *
     * @return Collection<int, Role>
     */
    public function list(): Collection
    {
        return Role::query()->orderBy('id')->get();
    }

    /**
     * List all roles with their permissions eager loaded.
     *
     * @return Collection<int, Role>
     */
    public function listWithPermissions(): Collection
    {
        return Role::query()
            ->with('permissions')
            ->orderBy('id')
            ->get();
    }

    /**
     * Create a new role.
     */
    public function store(string $name, User $createdBy): Role
    {
        $role = Role::create(['name' => $name]);

        Log::info('Role created', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'created_by' => $createdBy->id,
        ]);

        return $role;
    }
}
