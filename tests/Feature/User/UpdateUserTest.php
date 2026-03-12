<?php

namespace Tests\Feature\User;

use App\Models\User;

class UpdateUserTest extends UserManagementTestCase
{
    public function test_update_user_returns_401_when_unauthenticated(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $response = $this->patchJson('/api/users/' . $user->id, ['name' => 'Updated Name']);

        $response->assertStatus(401);
    }

    public function test_update_user_returns_403_when_regular_user_updates_another_user(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('User');
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson('/api/users/' . $otherUser->id, ['name' => 'Updated Name']);

        $response->assertStatus(403);
    }

    public function test_update_user_allows_user_to_update_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'me@example.com']);
        $user->assignRole('User');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson('/api/users/' . $user->id, [
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
                    'roles' => ['User'],
                ],
            ]);
        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('newemail@example.com', $user->email);
        $this->assertTrue($user->hasRole('User'));
    }

    public function test_update_user_cannot_change_own_role_when_not_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson('/api/users/' . $user->id, ['role' => 'Super Admin']);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertTrue($user->hasRole('User'));
        $this->assertFalse($user->hasRole('Super Admin'));
    }

    public function test_update_user_updates_fields_when_super_admin(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        $user->assignRole('User');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson('/api/users/' . $user->id, [
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

    public function test_update_user_syncs_role_when_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson('/api/users/' . $user->id, ['role' => 'Super Admin']);

        $response->assertStatus(200)
            ->assertJsonPath('data.roles', ['Super Admin']);
        $user->refresh();
        $this->assertTrue($user->hasRole('Super Admin'));
        $this->assertFalse($user->hasRole('User'));
    }

    public function test_update_user_returns_422_when_email_taken_by_another_user(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);
        $user->assignRole('User');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson('/api/users/' . $user->id, ['email' => 'taken@example.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_user_returns_422_when_role_does_not_exist(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson('/api/users/' . $user->id, ['role' => 'NonExistent Role']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }
}
