<?php

namespace Tests\Feature\User;

use App\Models\User;

class UpdateUserRoleTest extends UserManagementTestCase
{
    public function test_update_role_returns_401_when_unauthenticated(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $response = $this->patchJson(route('users.update-role', $user), ['role' => 'Super Admin']);

        $response->assertStatus(401);
    }

    public function test_update_role_returns_403_when_regular_user(): void
    {
        $targetUser = User::factory()->create();
        $targetUser->assignRole('User');
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.update-role', $targetUser), ['role' => 'Super Admin']);

        $response->assertStatus(403);
        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('User'));
    }

    public function test_update_role_succeeds_when_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.update-role', $user), ['role' => 'Super Admin']);

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
        $this->assertFalse($user->hasRole('User'));
    }

    public function test_update_role_returns_422_when_role_does_not_exist(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.update-role', $user), ['role' => 'NonExistent Role']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
        $user->refresh();
        $this->assertTrue($user->hasRole('User'));
    }

    public function test_update_role_returns_422_when_role_missing(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.update-role', $user), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }
}
