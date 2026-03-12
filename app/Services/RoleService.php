<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

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
     * Create a new role.
     */
    public function store(string $name, User $createdBy): Role
    {
        $guardName = config('auth.defaults.guard', 'web');
        $role = Role::create([
            'name' => $name,
            'guard_name' => $guardName,
        ]);

        Log::info('Role created', [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'created_by' => $createdBy->id,
        ]);

        return $role;
    }
}
