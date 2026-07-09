<?php

namespace PortalConnect\Subscriptions\DTO;

class InitiatedBill
{
    public function __construct(
        public readonly string $payUrl,
        public readonly ?int $paymentId = null,
    ) {
    }
}
