<?php

namespace Tests\Feature\User;

use App\Models\User;

class ListUsersTest extends UserManagementTestCase
{
    public function test_list_users_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson(route('users.view'));

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_list_users_returns_403_when_not_super_admin(): void
    {
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson(route('users.view'));

        $response->assertStatus(403);
    }

    public function test_list_users_returns_paginated_users_when_super_admin(): void
    {
        User::factory()->count(3)->create()->each(fn (User $u) => $u->assignRole('Resident'));
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson(route('users.view'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    ['id', 'name', 'email', 'email_verified_at', 'two_factor_enabled', 'two_factor_method', 'roles'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
                'links' => ['first', 'last', 'prev', 'next'],
            ])
            ->assertJson(['success' => true]);
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_list_users_respects_per_page_parameter(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson(route('users.view', ['per_page' => 2]));

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2);
    }
}
