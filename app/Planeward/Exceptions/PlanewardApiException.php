<?php

namespace App\Planeward\Exceptions;

use RuntimeException;

/** A Planeward API call returned a non-2xx status. */
class PlanewardApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
