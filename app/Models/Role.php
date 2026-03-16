<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Permissions assigned to this role.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    /**
     * Give one or more permissions to this role.
     *
     * @param  int|int[]|Permission|Permission[]  $permissions  Permission id(s) or model(s)
     * @return void
     */
    public function givePermissionTo(int|array|Permission $permissions): void
    {
        $ids = collect($permissions)
            ->map(fn ($p) => $p instanceof Permission ? $p->id : $p)
            ->all();

        $this->permissions()->syncWithoutDetaching($ids);
    }

    /**
     * Replace all permissions for this role with the given set.
     *
     * @param  int[]|Permission[]  $permissions  Permission id(s) or model(s)
     * @return void
     */
    public function syncPermissions(array $permissions): void
    {
        $ids = collect($permissions)
            ->map(fn ($p) => $p instanceof Permission ? $p->id : $p)
            ->all();

        $this->permissions()->sync($ids);
    }

    /**
     * Users that have this role.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_has_roles');
    }
}
