<?php

namespace Tests\Feature\Permission;

use Tests\Feature\User\UserManagementTestCase;

class ListPermissionsTest extends UserManagementTestCase
{
    public function test_list_permissions_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson(route('permissions.view'));

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_list_permissions_returns_403_when_user_lacks_permission(): void
    {
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson(route('permissions.view'));

        $response->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to perform this action.');
    }

    public function test_list_permissions_returns_all_permissions_when_super_admin(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson(route('permissions.view'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'description'],
                ],
            ])
            ->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $names = array_column($data, 'name');
        $this->assertContains('permissions.view', $names);
        $this->assertContains('users.view', $names);
    }
}
