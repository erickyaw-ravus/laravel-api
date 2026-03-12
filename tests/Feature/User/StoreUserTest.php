<?php

namespace Tests\Feature\User;

use App\Models\User;

class StoreUserTest extends UserManagementTestCase
{
    public function test_store_user_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_user_returns_403_when_not_super_admin(): void
    {
        $token = $this->actingAsRegularUser();

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(403);
    }

    public function test_store_user_creates_user_with_user_role_when_super_admin(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'User created',
                'data' => [
                    'name' => 'New User',
                    'email' => 'newuser@example.com',
                    'roles' => ['User'],
                ],
            ]);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_store_user_returns_422_when_email_already_taken(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'existing@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_user_returns_422_when_required_fields_missing(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson('/api/users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_store_user_returns_422_when_password_not_confirmed(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Different123!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
