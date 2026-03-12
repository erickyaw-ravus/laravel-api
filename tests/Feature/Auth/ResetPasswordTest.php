<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_RESET_PREFIX = 'password_reset:';

    public function test_reset_password_succeeds_with_valid_token(): void
    {
        $user = User::factory()->twoFactorDisabled()->create(['email' => 'user@example.com']);
        $token = 'valid-reset-token-64-chars-long-enough-to-match-length-requirement';
        Cache::put(self::PASSWORD_RESET_PREFIX . $token, ['user_id' => $user->id], 3600);

        $response = $this->postJson(route('password.reset'), [
            'token' => $token,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password has been reset. You can now log in with your new password.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-123', $user->password));
        $this->assertNull(Cache::get(self::PASSWORD_RESET_PREFIX . $token));
    }

    public function test_reset_password_returns_400_for_invalid_token(): void
    {
        $user = User::factory()->twoFactorDisabled()->create(['email' => 'user@example.com']);

        $response = $this->postJson(route('password.reset'), [
            'token' => 'invalid-token',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ]);
    }

    public function test_reset_password_returns_400_for_expired_token(): void
    {
        $user = User::factory()->twoFactorDisabled()->create(['email' => 'user@example.com']);
        $token = 'expired-reset-token-64-chars-long-enough-to-match-length-requirement';

        Cache::put(self::PASSWORD_RESET_PREFIX . $token, [
            'user_id' => $user->id,
            'expires_at' => now()->subMinutes(1)->timestamp,
        ], 3600);

        $response = $this->postJson(route('password.reset'), [
            'token' => $token,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Reset token has expired. Please request a new password reset link.',
            ]);

        $user->refresh();
        $this->assertFalse(Hash::check('new-secret-123', $user->password));
    }

    public function test_reset_password_returns_400_when_user_no_longer_exists(): void
    {
        $token = 'valid-reset-token-64-chars-long-enough-to-match-length-requirement';
        Cache::put(self::PASSWORD_RESET_PREFIX . $token, ['user_id' => 99999], 3600);

        $response = $this->postJson(route('password.reset'), [
            'token' => $token,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ]);
    }

    public function test_reset_password_returns_422_when_password_confirmation_mismatch(): void
    {
        $user = User::factory()->twoFactorDisabled()->create(['email' => 'user@example.com']);
        $token = 'valid-reset-token-64-chars-long-enough-to-match-length-requirement';
        Cache::put(self::PASSWORD_RESET_PREFIX . $token, ['user_id' => $user->id], 3600);

        $response = $this->postJson(route('password.reset'), [
            'token' => $token,
            'password' => 'new-secret-123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_returns_422_when_token_missing(): void
    {
        $response = $this->postJson(route('password.reset'), [
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }
}
