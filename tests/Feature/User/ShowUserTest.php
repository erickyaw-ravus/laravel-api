<?php

namespace Tests\Feature\User;

use App\Models\User;

class ShowUserTest extends UserManagementTestCase
{
    public function test_show_user_returns_401_when_unauthenticated(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $response = $this->getJson('/api/users/' . $user->id);

        $response->assertStatus(401);
    }

    public function test_show_user_returns_403_when_not_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson('/api/users/' . $user->id);

        $response->assertStatus(403);
    }

    public function test_show_user_returns_user_detail_with_roles_when_super_admin(): void
    {
        $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user->assignRole('User');
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson('/api/users/' . $user->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'email', 'email_verified_at', 'two_factor_enabled', 'two_factor_method', 'roles'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => 'Alice',
                    'email' => 'alice@example.com',
                    'roles' => ['User'],
                ],
            ]);
    }
}
