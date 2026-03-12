<?php

namespace App\Http\Controllers;

use App\Exceptions\AuthException;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Handle login. When two_factor_enabled and two_factor_method is email,
     * sends a code by email and returns a two_factor_token to verify instead of a session token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->login(
                $request->validated('email'),
                $request->validated('password')
            );
        } catch (AuthException $e) {
            return $this->handleAuthException($e);
        }

        if (! empty($data['requires_two_factor'])) {
            return ApiResponse::success([
                'requires_two_factor' => true,
                'two_factor_token' => $data['two_factor_token'],
            ], $data['message']);
        }

        return ApiResponse::success([
            'token' => $data['token'],
            'user' => new UserResource($data['user']),
        ]);
    }

    /**
     * Verify two-factor code and issue an API token.
     */
    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->verifyTwoFactor(
                $request->validated('two_factor_token'),
                $request->validated('code')
            );
        } catch (AuthException $e) {
            return ApiResponse::error($e->getMessage(), $e->getStatusCode());
        }

        return ApiResponse::success([
            'token' => $data['token'],
            'user' => new UserResource($data['user']),
        ]);
    }

    /**
     * Send a password reset link to the user's email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        return ApiResponse::success(null, 'If that email is registered, we have sent a password reset link.');
    }

    /**
     * Reset password using the token from the email link.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword(
                $request->validated('token'),
                $request->validated('password')
            );
        } catch (AuthException $e) {
            return ApiResponse::error($e->getMessage(), $e->getStatusCode());
        }

        return ApiResponse::success(null, 'Password has been reset. You can now log in with your new password.');
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(null, 'Logged out');
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user(), $request->validated('password'));

        return ApiResponse::success(null, 'Password changed successfully');
    }

    private function handleAuthException(AuthException $e): JsonResponse
    {
        $validationErrors = $e->getValidationErrors();
        if ($validationErrors !== null) {
            throw ValidationException::withMessages($validationErrors);
        }

        return ApiResponse::error($e->getMessage(), $e->getStatusCode());
    }
}
