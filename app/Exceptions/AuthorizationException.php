<?php

namespace App\Exceptions;

class AuthorizationException extends ApiException
{
    protected int $statusCode = 403;

    public function __construct(string $message = 'Unauthorized action.', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
