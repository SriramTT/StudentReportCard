<?php

namespace App\Exceptions;

use RuntimeException;

class SystemAlreadyInitializedException extends RuntimeException
{
    public function __construct(string $message = 'First-run setup has already been completed.')
    {
        parent::__construct($message);
    }
}
