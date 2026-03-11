<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_succeeds_with_valid_input(): void
    {
        $user = User::factory()->twoFactorDisabled()->create([
            'password' => 'current-secret',
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/password/change', [
                'current_password' => 'current-secret',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-123', $user->password));
    }

    public function test_change_password_returns_422_when_current_password_incorrect(): void
    {
        $user = User::factory()->twoFactorDisabled()->create([
            'password' => 'current-secret',
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/password/change', [
                'current_password' => 'wrong-password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_change_password_returns_422_when_password_confirmation_mismatch(): void
    {
        $user = User::factory()->twoFactorDisabled()->create([
            'password' => 'current-secret',
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/password/change', [
                'current_password' => 'current-secret',
                'password' => 'new-secret-123',
                'password_confirmation' => 'different',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/password/change', [
            'current_password' => 'current-secret',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertStatus(401);
    }
}
