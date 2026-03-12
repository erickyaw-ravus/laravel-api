<?php

namespace Tests\Feature\Role;

use Tests\Feature\User\UserManagementTestCase;

class ListRolesTest extends UserManagementTestCase
{
    public function test_list_roles_returns_403_when_not_super_admin(): void
    {
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson('/api/roles');

        $response->assertForbidden();
    }

    public function test_list_roles_returns_all_roles_without_pagination_when_super_admin(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson('/api/roles');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name'],
                ],
            ])
            ->assertJsonPath('success', true);
        $this->assertArrayNotHasKey('meta', $response->json());
        $this->assertArrayNotHasKey('links', $response->json());
    }
}
