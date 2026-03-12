<?php

namespace Tests\Unit\Services;

use App\Exceptions\AuthException;
use App\Mail\PasswordResetMail;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    private const PASSWORD_RESET_PREFIX = 'password_reset:';

    private const TWO_FACTOR_PREFIX = '2fa:';

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = app(AuthService::class);
    }

    public function test_login_throws_auth_exception_for_invalid_credentials(): void
    {
        User::factory()->twoFactorDisabled()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correct'),
        ]);

        try {
            $this->authService->login('user@example.com', 'wrong-password');
            $this->fail('Expected AuthException');
        } catch (AuthException $e) {
            $this->assertSame('The provided credentials are incorrect.', $e->getMessage());
            $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getStatusCode());
            $this->assertSame(['email' => ['The provided credentials are incorrect.']], $e->getValidationErrors());
        }
    }

    public function test_login_returns_token_and_user_when_two_factor_disabled(): void
    {
        $user = User::factory()->twoFactorDisabled()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $result = $this->authService->login('user@example.com', 'password123');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame($user->id, $result['user']->id);
        $this->assertNotEmpty($result['token']);
    }

    public function test_login_returns_two_factor_payload_and_sends_email_when_2fa_enabled(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
            'two_factor_enabled' => true,
            'two_factor_method' => 'email',
        ]);

        $result = $this->authService->login('user@example.com', 'password123');

        $this->assertTrue($result['requires_two_factor'] ?? false);
        $this->assertArrayHasKey('two_factor_token', $result);
        $this->assertSame('Verification code sent to your email', $result['message']);
        $this->assertNotNull(Cache::get(self::TWO_FACTOR_PREFIX.$user->id));

        Mail::assertQueued(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
    }

    public function test_verify_two_factor_throws_for_invalid_token(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired verification token.');
        $this->authService->verifyTwoFactor('invalid-token', '123456');
    }

    public function test_verify_two_factor_throws_for_expired_token(): void
    {
        $user = User::factory()->create();
        $expiredToken = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->subMinutes(1)->timestamp,
        ]);
        Cache::put(self::TWO_FACTOR_PREFIX.$user->id, '123456', 600);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Verification token has expired.');
        $this->authService->verifyTwoFactor($expiredToken, '123456');
    }

    public function test_verify_two_factor_throws_for_wrong_code(): void
    {
        $user = User::factory()->create();
        $token = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
        Cache::put(self::TWO_FACTOR_PREFIX.$user->id, '123456', 600);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired verification code.');
        $this->authService->verifyTwoFactor($token, '000000');
    }

    public function test_verify_two_factor_returns_token_and_user_and_forgets_cache(): void
    {
        $user = User::factory()->create();
        $token = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);
        $code = '123456';
        Cache::put(self::TWO_FACTOR_PREFIX.$user->id, $code, 600);

        $result = $this->authService->verifyTwoFactor($token, $code);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertSame($user->id, $result['user']->id);
        $this->assertNull(Cache::get(self::TWO_FACTOR_PREFIX.$user->id));
    }

    public function test_forgot_password_does_nothing_for_unknown_email(): void
    {
        Mail::fake();

        $this->authService->forgotPassword('unknown@example.com');

        Mail::assertNothingSent();
    }

    public function test_forgot_password_sends_email_and_stores_token_for_known_email(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://app.test']);
        $user = User::factory()->twoFactorDisabled()->create(['email' => 'user@example.com']);

        $this->authService->forgotPassword('user@example.com');

        Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user): bool {
            $mail->assertTo($user->email);
            $this->assertStringContainsString('/reset-password', $mail->resetLink);
            $this->assertStringContainsString('token=', $mail->resetLink);
            return true;
        });

        $link = null;
        Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$link): bool {
            $link = $mail->resetLink;
            return true;
        });
        $this->assertNotNull($link);
        /** @var string $link */
        $params = [];
        parse_str((string) (parse_url($link, PHP_URL_QUERY) ?: ''), $params);
        $this->assertArrayHasKey('token', $params);
        $token = is_string($params['token'] ?? null) ? $params['token'] : '';
        $this->assertNotSame('', $token, 'Reset link should contain a token');
        $this->assertNotNull(Cache::get(self::PASSWORD_RESET_PREFIX . $token));
    }

    public function test_reset_password_throws_for_invalid_token(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Invalid or expired reset token.');
        $this->authService->resetPassword('invalid-token', 'newpassword123');
    }

    public function test_reset_password_throws_for_expired_token(): void
    {
        $user = User::factory()->twoFactorDisabled()->create();
        $token = 'my-token-64-chars-long-enough-to-match-requirement--------';
        Cache::put(self::PASSWORD_RESET_PREFIX.$token, [
            'user_id' => $user->id,
            'expires_at' => now()->subMinutes(1)->timestamp,
        ], 3600);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Reset token has expired.');
        $this->authService->resetPassword($token, 'newpassword123');
    }

    public function test_reset_password_updates_password_and_forgets_token(): void
    {
        $user = User::factory()->twoFactorDisabled()->create(['email' => 'user@example.com']);
        $token = 'my-token-64-chars-long-enough-to-match-requirement--------';
        Cache::put(self::PASSWORD_RESET_PREFIX.$token, [
            'user_id' => $user->id,
            'expires_at' => now()->addHour()->timestamp,
        ], 3600);

        $this->authService->resetPassword($token, 'newpassword123');

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertNull(Cache::get(self::PASSWORD_RESET_PREFIX.$token));
    }

    public function test_change_password_updates_user_password(): void
    {
        $user = User::factory()->twoFactorDisabled()->create(['password' => 'oldpass']);

        $this->authService->changePassword($user, 'newpass456');

        $user->refresh();
        $this->assertTrue(Hash::check('newpass456', $user->password));
    }

    public function test_logout_deletes_current_access_token(): void
    {
        $user = User::factory()->twoFactorDisabled()->create();
        $newToken = $user->createToken('test');
        $user->withAccessToken($newToken->accessToken);
        $this->assertCount(1, $user->tokens);

        $this->authService->logout($user);

        $user->refresh();
        $this->assertCount(0, $user->tokens);
    }
}
