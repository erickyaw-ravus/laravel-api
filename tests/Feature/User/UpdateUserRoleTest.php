<?php

namespace Tests\Feature\User;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class UpdateUserRoleTest extends UserManagementTestCase
{
    public function test_update_role_returns_401_when_unauthenticated(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Resident');

        $response = $this->patchJson(route('users.edit-role', $user), ['roles' => ['Super Admin']]);

        $response->assertStatus(401);
    }

    public function test_update_role_returns_403_when_regular_user(): void
    {
        $targetUser = User::factory()->create();
        $targetUser->assignRole('Resident');
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit-role', $targetUser), ['roles' => ['Super Admin']]);

        $response->assertStatus(403);
        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('Resident'));
    }

    /**
     * User without users.edit-role cannot update even their own role (403 from permission middleware).
     */
    public function test_update_role_returns_403_when_user_lacks_users_edit_role_permission_even_own_profile(): void
    {
        $roleWithoutEditRole = Role::firstOrCreate(['name' => 'Viewer']);
        $roleWithoutEditRole->syncPermissions([]);
        $user = User::factory()->twoFactorDisabled()->create();
        $user->assignRole('Viewer');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit-role', $user), ['roles' => ['Resident']]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ]);
        $user->refresh();
        $this->assertTrue($user->hasRole('Viewer'));
    }

    /**
     * User with users.edit-role can update only their own role.
     */
    public function test_update_role_allows_user_with_users_edit_role_to_update_own_role(): void
    {
        $roleWithEditRole = Role::firstOrCreate(['name' => 'Manager']);
        $roleWithEditRole->syncPermissions(
            Permission::whereIn('name', ['users.edit-role'])->pluck('id')->all()
        );
        $user = User::factory()->twoFactorDisabled()->create();
        $user->assignRole('Manager');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit-role', $user), ['roles' => ['Resident']]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User role updated',
                'data' => [
                    'id' => $user->id,
                    'roles' => ['Resident'],
                ],
            ]);
        $user->refresh();
        $this->assertTrue($user->hasRole('Resident'));
        $this->assertFalse($user->hasRole('Manager'));
    }

    /**
     * User with users.edit-role cannot update another user's role (policy forbids).
     */
    public function test_update_role_returns_403_when_user_with_users_edit_role_updates_another_user(): void
    {
        $roleWithEditRole = Role::firstOrCreate(['name' => 'Manager']);
        $roleWithEditRole->syncPermissions(
            Permission::whereIn('name', ['users.edit-role'])->pluck('id')->all()
        );
        $editor = User::factory()->twoFactorDisabled()->create();
        $editor->assignRole('Manager');
        $targetUser = User::factory()->create();
        $targetUser->assignRole('Resident');
        $token = $editor->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit-role', $targetUser), ['roles' => ['Super Admin']]);

        $response->assertStatus(403);
        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('Resident'));
        $this->assertFalse($targetUser->hasRole('Super Admin'));
    }

    /**
     * Super admin can update any user's role.
     */
    public function test_update_role_succeeds_when_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Resident');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit-role', $user), ['roles' => ['Super Admin']]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User role updated',
                'data' => [
                    'id' => $user->id,
                    'roles' => ['Super Admin'],
                ],
            ]);
        $user->refresh();
        $this->assertTrue($user->hasRole('Super Admin'));
        $this->assertFalse($user->hasRole('Resident'));
    }

    public function test_update_role_returns_422_when_role_does_not_exist(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Resident');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit-role', $user), ['roles' => ['NonExistent Role']]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
        $user->refresh();
        $this->assertTrue($user->hasRole('Resident'));
    }

    public function test_update_role_returns_422_when_role_missing(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Resident');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit-role', $user), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles']);
    }
}
