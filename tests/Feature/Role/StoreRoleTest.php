<?php

namespace Tests\Feature\Role;

use App\Models\Role;
use Tests\Feature\User\UserManagementTestCase;

class StoreRoleTest extends UserManagementTestCase
{
    public function test_store_role_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson(route('roles.create'), ['name' => 'Manager']);

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_store_role_returns_403_when_not_super_admin(): void
    {
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson(route('roles.create'), ['name' => 'Manager']);

        $response->assertForbidden();
    }

    public function test_store_role_creates_role_when_super_admin(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson(route('roles.create'), ['name' => 'Manager']);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'name']])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Manager');
        $this->assertDatabaseHas('roles', ['name' => 'Manager']);
    }

    public function test_store_role_returns_422_when_name_missing(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson(route('roles.create'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_role_returns_422_when_name_duplicate(): void
    {
        $token = $this->actingAsSuperAdmin();
        Role::create(['name' => 'Manager']);

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson(route('roles.create'), ['name' => 'Manager']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
