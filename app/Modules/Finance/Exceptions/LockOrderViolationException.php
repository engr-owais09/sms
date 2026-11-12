<?php

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

class LockOrderViolationException extends RuntimeException
{
    public function __construct(string $message = 'Financial lock order violation detected.')
    {
        parent::__construct($message);
    }
}

