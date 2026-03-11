<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Mail\PasswordResetMail;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    private const TWO_FACTOR_CACHE_PREFIX = '2fa:';

    private const TWO_FACTOR_CODE_TTL_SECONDS = 10 * Carbon::SECONDS_PER_MINUTE; // 10 minutes

    private const PASSWORD_RESET_CACHE_PREFIX = 'password_reset:';

    private const PASSWORD_RESET_TTL_SECONDS = 60 * Carbon::SECONDS_PER_MINUTE; // 60 minutes

    /**
     * Handle login. When two_factor_enabled and two_factor_method is email,
     * sends a code by email and returns a two_factor_token to verify instead of a session token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->two_factor_enabled && $user->two_factor_method === 'email') {
            $code = (string) random_int(100000, 999999);
            Cache::put(self::TWO_FACTOR_CACHE_PREFIX . $user->id, $code, self::TWO_FACTOR_CODE_TTL_SECONDS);
            Mail::to($user)->send(new TwoFactorCodeMail($user, $code));

            $twoFactorToken = encrypt([
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]);

            return ApiResponse::success([
                'requires_two_factor' => true,
                'two_factor_token' => $twoFactorToken,
            ], 'Verification code sent to your email');
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Verify two-factor code and issue an API token.
     */
    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        try {
            $payload = decrypt($request->validated('two_factor_token'));
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return ApiResponse::error('Invalid or expired verification token.', Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($payload) || !isset($payload['user_id'], $payload['expires_at'])) {
            return ApiResponse::error('Invalid verification token.', Response::HTTP_BAD_REQUEST);
        }

        if ($payload['expires_at'] < time()) {
            return ApiResponse::error('Verification token has expired. Please log in again.', Response::HTTP_BAD_REQUEST);
        }

        $user = User::find($payload['user_id']);
        if (!$user) {
            return ApiResponse::error('User not found.', Response::HTTP_BAD_REQUEST);
        }

        $cachedCode = Cache::get(self::TWO_FACTOR_CACHE_PREFIX . $user->id);
        if ($cachedCode === null || $cachedCode !== $request->validated('code')) {
            return ApiResponse::error('Invalid or expired verification code.', Response::HTTP_BAD_REQUEST);
        }

        Cache::forget(self::TWO_FACTOR_CACHE_PREFIX . $user->id);

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Send a password reset link to the user's email. Link points to the frontend
     * with a token; the frontend should call POST /password/reset with token and new password.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user) {
            $token = Str::random(64);
            $expiresAt = now()->addSeconds(self::PASSWORD_RESET_TTL_SECONDS)->timestamp;
            Cache::put(
                self::PASSWORD_RESET_CACHE_PREFIX . $token,
                ['user_id' => $user->id, 'expires_at' => $expiresAt],
                self::PASSWORD_RESET_TTL_SECONDS
            );

            $frontendUrl = rtrim(config('app.frontend_url'), '/');
            $resetLink = $frontendUrl . '/reset-password?token=' . urlencode($token);

            Mail::to($user)->send(new PasswordResetMail($user, $resetLink));
        }

        return ApiResponse::success(null, 'If that email is registered, we have sent a password reset link.');
    }

    /**
     * Reset password using the token from the email link.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $payload = Cache::get(self::PASSWORD_RESET_CACHE_PREFIX . $request->validated('token'));

        if (!$payload || !is_array($payload) || !isset($payload['user_id'])) {
            return ApiResponse::error('Invalid or expired reset token.', Response::HTTP_BAD_REQUEST);
        }

        if (isset($payload['expires_at']) && $payload['expires_at'] < time()) {
            return ApiResponse::error('Reset token has expired. Please request a new password reset link.', Response::HTTP_BAD_REQUEST);
        }

        $user = User::find($payload['user_id']);
        if (!$user) {
            return ApiResponse::error('Invalid or expired reset token.', Response::HTTP_BAD_REQUEST);
        }

        $user->password = $request->validated('password');
        $user->save();

        Cache::forget(self::PASSWORD_RESET_CACHE_PREFIX . $request->validated('token'));

        return ApiResponse::success(null, 'Password has been reset. You can now log in with your new password.');
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out');
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        return ApiResponse::success(null, 'Password changed successfully');
    }
}
