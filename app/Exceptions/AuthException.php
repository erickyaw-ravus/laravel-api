<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class AuthException extends Exception
{
    /**
     * @param  array<string, list<string>>|null  $validationErrors  When set, controller may respond with validation-style JSON.
     */
    public function __construct(
        string $message = 'Authentication failed',
        int $statusCode = Response::HTTP_BAD_REQUEST,
        private ?array $validationErrors = null
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return (int) $this->code;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function getValidationErrors(): ?array
    {
        return $this->validationErrors;
    }
}
