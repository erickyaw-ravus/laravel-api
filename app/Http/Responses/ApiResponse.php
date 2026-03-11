<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse
{
    /**
     * Return a successful JSON response.
     *
     * @param  array<string, mixed>|object|null  $data
     */
    public static function success(
        array|object|null $data = null,
        string $message = '',
        int $status = Response::HTTP_OK
    ): JsonResponse {
        $payload = [
            'success' => true,
        ];

        if ($message !== '') {
            $payload['message'] = $message;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return an error JSON response.
     *
     * @param  array<string, mixed>|null  $errors  Validation or field-level errors.
     */
    public static function error(
        string $message = 'An error occurred',
        int $status = Response::HTTP_BAD_REQUEST,
        ?array $errors = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
