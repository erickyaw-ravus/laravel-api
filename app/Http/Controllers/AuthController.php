<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    private const TWO_FACTOR_CACHE_PREFIX = '2fa:';

    private const TWO_FACTOR_CODE_TTL_SECONDS = 10 * Carbon::SECONDS_PER_MINUTE; // 10 minutes

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
