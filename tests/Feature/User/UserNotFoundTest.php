<?php

namespace Tests\Feature\User;

/**
 * Tests that API routes using route model binding return a consistent 404 JSON
 * when the resource does not exist (no exception details, file paths, or trace).
 */
class UserNotFoundTest extends UserManagementTestCase
{
    private const NON_EXISTENT_USER_ID = 99999;

    public function test_show_user_returns_404_with_clean_json_when_user_missing(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->getJson(route('users.detail', ['user' => self::NON_EXISTENT_USER_ID]));

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found',
            ])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');
    }

    public function test_update_user_returns_404_with_clean_json_when_user_missing(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit', ['user' => self::NON_EXISTENT_USER_ID]), [
                'name' => 'Updated',
                'email' => 'updated@example.com',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found',
            ])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');
    }

    public function test_update_user_role_returns_404_with_clean_json_when_user_missing(): void
    {
        $token = $this->actingAsSuperAdmin();

        $response = $this->withHeaders($this->authHeader($token))
            ->patchJson(route('users.edit-role', ['user' => self::NON_EXISTENT_USER_ID]), ['roles' => ['User']]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found',
            ])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');
    }
}
