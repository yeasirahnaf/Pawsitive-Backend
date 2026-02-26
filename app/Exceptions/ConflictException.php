<?php

namespace App\Exceptions;

class ConflictException extends ApiException
{
    protected int $statusCode = 409;

    public function __construct(string $message = 'Resource conflict.', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
