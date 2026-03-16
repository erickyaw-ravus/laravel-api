<?php

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VerifyTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_two_factor_succeeds_with_valid_token_and_code(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
            'two_factor_enabled' => true,
            'two_factor_method' => 'email',
        ]);

        $loginResponse = $this->postJson(route('login'), [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $twoFactorToken = $loginResponse->json('data.two_factor_token');

        $sentCode = null;
        Mail::assertQueued(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$sentCode) {
            $sentCode = $mail->code;

            return true;
        });
        if ($sentCode === null) {
            $this->fail('Expected a two-factor code to be sent by email.');
        }
        $this->assertSame(6, strlen($sentCode));

        $response = $this->postJson(route('login.verify-two-factor'), [
            'two_factor_token' => $twoFactorToken,
            'code' => $sentCode,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'email_verified_at'],
                ],
            ]);
    }

    public function test_verify_two_factor_fails_with_invalid_code(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
            'two_factor_enabled' => true,
            'two_factor_method' => 'email',
        ]);

        $loginResponse = $this->postJson(route('login'), [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $twoFactorToken = $loginResponse->json('data.two_factor_token');

        $response = $this->postJson(route('login.verify-two-factor'), [
            'two_factor_token' => $twoFactorToken,
            'code' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ]);
    }

    public function test_verify_two_factor_fails_with_expired_token(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_method' => 'email',
        ]);

        $expiredToken = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->subMinutes(1)->timestamp,
        ]);

        $response = $this->postJson(route('login.verify-two-factor'), [
            'two_factor_token' => $expiredToken,
            'code' => '123456',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Verification token has expired. Please log in again.',
            ]);
    }

    public function test_verify_two_factor_fails_with_invalid_token(): void
    {
        $response = $this->postJson(route('login.verify-two-factor'), [
            'two_factor_token' => 'invalid-token',
            'code' => '123456',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_verify_two_factor_fails_with_missing_fields(): void
    {
        $response = $this->postJson(route('login.verify-two-factor'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['two_factor_token', 'code']);
    }

    public function test_verify_two_factor_fails_with_invalid_code_format(): void
    {
        $user = User::factory()->create();
        $token = encrypt([
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $response = $this->postJson(route('login.verify-two-factor'), [
            'two_factor_token' => $token,
            'code' => 'abc',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }
}
