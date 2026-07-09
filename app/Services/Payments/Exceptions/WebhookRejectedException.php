<?php

namespace App\Services\Payments\Exceptions;

use Exception;

class WebhookRejectedException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 403,
    ) {
        parent::__construct($message);
    }
}
