<?php

namespace App\Exceptions;

class BusinessLogicException extends ApiException
{
    protected int $statusCode = 400;

    public function __construct(string $message = 'Business logic error.', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
