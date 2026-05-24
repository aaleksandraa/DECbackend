<?php

namespace App\Exceptions;

use RuntimeException;

class BookingUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        private string $reasonCode,
    ) {
        parent::__construct($message);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
