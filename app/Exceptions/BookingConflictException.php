<?php

namespace App\Exceptions;

use RuntimeException;

class BookingConflictException extends RuntimeException
{
    public function __construct(
        string $message = 'Selected slot is no longer available',
        private string $reasonCode = 'TIME_SLOT_TAKEN',
    )
    {
        parent::__construct($message);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
