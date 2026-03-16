<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The user's roles (many-to-many).
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_has_roles');
    }

    /**
     * Assign a role to the user by name (adds without removing existing roles).
     */
    public function assignRole(string $roleName): void
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Replace all roles for the user with the given set of role names.
     *
     * @param  string[]  $roleNames
     */
    public function syncRoles(array $roleNames): void
    {
        $ids = Role::query()->whereIn('name', $roleNames)->pluck('id')->all();
        $this->roles()->sync($ids);
    }

    /**
     * Check if the user has a role by name.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if the user has a permission by name (via any of their roles).
     */
    public function hasPermissionTo(string $permissionName): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permissionName))
            ->exists();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_enabled',
        'two_factor_method',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
        ];
    }
}
