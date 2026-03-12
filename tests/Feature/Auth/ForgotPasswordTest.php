<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_email_and_returns_success(): void
    {
        Mail::fake();
        $user = User::factory()->twoFactorDisabled()->create(['email' => 'user@example.com']);

        $response = $this->postJson(route('password.forgot'), [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'If that email is registered, we have sent a password reset link.',
            ]);

        Mail::assertQueued(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user): bool {
            $mail->assertTo($user->email);
            $this->assertStringContainsString('/reset-password', $mail->resetLink);
            $this->assertStringContainsString('token=', $mail->resetLink);
            return true;
        });
    }

    public function test_forgot_password_returns_same_message_for_unknown_email(): void
    {
        Mail::fake();

        $response = $this->postJson(route('password.forgot'), [
            'email' => 'unknown@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'If that email is registered, we have sent a password reset link.',
            ]);

        Mail::assertNotQueued(PasswordResetMail::class);
    }

    public function test_forgot_password_returns_422_when_email_invalid(): void
    {
        $response = $this->postJson(route('password.forgot'), [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_returns_422_when_email_missing(): void
    {
        $response = $this->postJson(route('password.forgot'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
