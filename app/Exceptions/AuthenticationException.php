<?php

namespace App\Exceptions;

class AuthenticationException extends ApiException
{
    protected int $statusCode = 401;

    public function __construct(string $message = 'Authentication failed.', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
