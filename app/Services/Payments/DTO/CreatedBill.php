<?php

namespace App\Services\Payments\DTO;

class CreatedBill
{
    public function __construct(
        public readonly ?string $externalId,
        public readonly string $payUrl,
    ) {
    }
}
