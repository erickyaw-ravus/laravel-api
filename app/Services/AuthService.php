<?php

namespace App\Services;

use App\Exceptions\AuthException;
use App\Mail\PasswordResetMail;
use App\Mail\TwoFactorCodeMail;
use App\Models\TwoFactorVerificationCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthService
{
    private const PASSWORD_RESET_TABLE = 'password_reset_tokens';

    private const PASSWORD_RESET_TTL_MINUTES = 60;

    private const TWO_FACTOR_CODE_TTL_MINUTES = 10;

    /**
     * Attempt login. Returns data for either token response or two-factor flow.
     *
     * @return array{token: string, user: User}|array{requires_two_factor: true, two_factor_token: string, message: string}
     *
     * @throws AuthException
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            Log::warning('Failed login attempt', ['email' => $email]);
            throw new AuthException(
                'The provided credentials are incorrect.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['email' => ['The provided credentials are incorrect.']]
            );
        }

        if ($user->two_factor_enabled && $user->two_factor_method === 'email') {
            return $this->sendTwoFactorCodeAndReturnPayload($user);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Verify two-factor code and return token and user.
     *
     * @return array{token: string, user: User}
     *
     * @throws AuthException
     */
    public function verifyTwoFactor(string $twoFactorToken, string $code): array
    {
        try {
            $payload = decrypt($twoFactorToken);
        } catch (DecryptException $e) {
            Log::warning('Two-factor verification failed: invalid or expired token', [
                'message' => $e->getMessage(),
            ]);
            throw new AuthException('Invalid or expired verification token.', Response::HTTP_BAD_REQUEST);
        }

        if (! is_array($payload) || ! isset($payload['user_id'], $payload['expires_at'])) {
            Log::warning('Two-factor verification failed: invalid token payload');
            throw new AuthException('Invalid verification token.', Response::HTTP_BAD_REQUEST);
        }

        if ($payload['expires_at'] < time()) {
            Log::warning('Two-factor verification failed: token expired', ['user_id' => $payload['user_id']]);
            throw new AuthException('Verification token has expired. Please log in again.', Response::HTTP_BAD_REQUEST);
        }

        $user = User::find($payload['user_id']);
        if (! $user) {
            Log::warning('Two-factor verification failed: user not found', ['user_id' => $payload['user_id']]);
            throw new AuthException('User not found.', Response::HTTP_BAD_REQUEST);
        }

        $record = TwoFactorVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record || ! Hash::check($code, $record->code)) {
            Log::warning('Two-factor verification failed: invalid or expired code', ['user_id' => $user->id]);
            throw new AuthException('Invalid or expired verification code.', Response::HTTP_BAD_REQUEST);
        }

        $record->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Send password reset email if user exists. Always returns success payload (no leak of existence).
     */
    public function forgotPassword(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        DB::table(self::PASSWORD_RESET_TABLE)->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $tokenHash,
                'created_at' => now(),
            ]
        );

        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $resetLink = $frontendUrl.'/reset-password?token='.urlencode($token);

        try {
            Mail::to($user)->send(new PasswordResetMail($user, $resetLink));
            Log::info('Password reset requested', ['user_id' => $user->id]);
        } catch (\Throwable $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reset password using the token from the email link.
     *
     * @throws AuthException
     */
    public function resetPassword(string $token, string $password): void
    {
        $tokenHash = hash('sha256', $token);
        $row = DB::table(self::PASSWORD_RESET_TABLE)
            ->where('token', $tokenHash)
            ->first();

        if (! $row) {
            throw new AuthException('Invalid or expired reset token.', Response::HTTP_BAD_REQUEST);
        }

        $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;
        if (! $createdAt || $createdAt->addMinutes(self::PASSWORD_RESET_TTL_MINUTES)->isPast()) {
            DB::table(self::PASSWORD_RESET_TABLE)->where('email', $row->email)->delete();
            throw new AuthException(
                'Reset token has expired. Please request a new password reset link.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = User::where('email', $row->email)->first();
        if (! $user) {
            DB::table(self::PASSWORD_RESET_TABLE)->where('email', $row->email)->delete();
            throw new AuthException('Invalid or expired reset token.', Response::HTTP_BAD_REQUEST);
        }

        $user->password = $password;
        $user->save();

        DB::table(self::PASSWORD_RESET_TABLE)->where('email', $row->email)->delete();

        Log::info('Password reset completed', ['user_id' => $user->id]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();

        Log::info('Password changed', ['user_id' => $user->id]);
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(User $user): void
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $user->currentAccessToken();
        $token->delete();
    }

    /**
     * @return array{requires_two_factor: true, two_factor_token: string, message: string}
     */
    private function sendTwoFactorCodeAndReturnPayload(User $user): array
    {
        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(self::TWO_FACTOR_CODE_TTL_MINUTES);

        TwoFactorVerificationCode::query()
            ->where('user_id', $user->id)
            ->delete();

        TwoFactorVerificationCode::create([
            'user_id' => $user->id,
            'code' => Hash::make($code),
            'expires_at' => $expiresAt,
        ]);

        try {
            Mail::to($user)->send(new TwoFactorCodeMail($user, $code));
        } catch (\Throwable $e) {
            Log::error('Failed to send two-factor code email', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $twoFactorToken = encrypt([
            'user_id' => $user->id,
            'expires_at' => $expiresAt->timestamp,
        ]);

        return [
            'requires_two_factor' => true,
            'two_factor_token' => $twoFactorToken,
            'message' => 'Verification code sent to your email',
        ];
    }
}
