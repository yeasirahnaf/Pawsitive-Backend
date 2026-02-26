<?php

namespace App\Exceptions;

class ValidationException extends ApiException
{
    protected int $statusCode = 422;

    public function __construct(string $message = 'Validation failed.', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
