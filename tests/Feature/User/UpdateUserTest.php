<?php

namespace Tests\Feature\User;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class UpdateUserTest extends UserManagementTestCase
{
    public function test_update_user_returns_401_when_unauthenticated(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Resident');

        $response = $this->patchJson(route('users.edit', $user), ['name' => 'Updated Name']);

        $response->assertStatus(401);
    }

    public function test_update_user_returns_403_when_regular_user_updates_another_user(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('Resident');
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', $otherUser), ['name' => 'Updated Name']);

        $response->assertStatus(403);
    }

    /**
     * User with users.edit can only edit their own profile; editing another user is forbidden.
     */
    public function test_update_user_returns_403_when_user_with_users_edit_edits_another_user(): void
    {
        $editor = User::factory()->twoFactorDisabled()->create();
        $editor->assignRole('Resident'); // Resident role has users.edit
        $targetUser = User::factory()->create(['name' => 'Other User']);
        $targetUser->assignRole('Resident');
        $token = $editor->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', $targetUser), ['name' => 'Hacked']);

        $response->assertStatus(403);
        $targetUser->refresh();
        $this->assertSame('Other User', $targetUser->name);
    }

    /**
     * User without users.edit permission cannot edit even their own profile (403 from permission middleware).
     */
    public function test_update_user_returns_403_when_user_lacks_users_edit_permission_even_own_profile(): void
    {
        $roleWithoutEdit = Role::firstOrCreate(['name' => 'Viewer']);
        $roleWithoutEdit->syncPermissions([]);
        $user = User::factory()->twoFactorDisabled()->create(['name' => 'Me', 'email' => 'me@example.com']);
        $user->assignRole('Viewer');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', $user), ['name' => 'Updated Me']);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
            ]);
        $user->refresh();
        $this->assertSame('Me', $user->name);
    }

    public function test_update_user_allows_user_to_update_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'me@example.com']);
        $user->assignRole('Resident');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', $user), [
                'name' => 'New Name',
                'email' => 'newemail@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User updated',
                'data' => [
                    'id' => $user->id,
                    'name' => 'New Name',
                    'email' => 'newemail@example.com',
                    'roles' => ['Resident'],
                ],
            ]);
        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('newemail@example.com', $user->email);
        $this->assertTrue($user->hasRole('Resident'));
    }

    public function test_update_user_ignores_role_field_role_unchanged(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Resident');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', $user), ['roles' => ['Super Admin']]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertTrue($user->hasRole('Resident'));
        $this->assertFalse($user->hasRole('Super Admin'));
    }

    public function test_update_user_updates_fields_when_super_admin(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        $user->assignRole('Resident');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', $user), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User updated',
                'data' => [
                    'id' => $user->id,
                    'name' => 'New Name',
                    'email' => 'new@example.com',
                ],
            ]);
        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_update_user_does_not_change_role_even_when_super_admin_sends_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Resident');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', $user), ['roles' => ['Super Admin']]);

        $response->assertStatus(200)
            ->assertJsonPath('data.roles', ['Resident']);
        $user->refresh();
        $this->assertTrue($user->hasRole('Resident'));
        $this->assertFalse($user->hasRole('Super Admin'));
    }

    public function test_update_user_returns_422_when_email_taken_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $user->assignRole('Resident');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', $user), ['email' => 'taken@example.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

}
