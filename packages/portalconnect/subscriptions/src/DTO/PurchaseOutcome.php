<?php

namespace PortalConnect\Subscriptions\DTO;

use PortalConnect\Subscriptions\Models\Subscription;

/** Результат покупки: активирована с баланса либо требуется оплата по ссылке. */
class PurchaseOutcome
{
    private function __construct(
        public readonly Subscription $subscription,
        public readonly bool $activated,
        public readonly ?InitiatedBill $bill,
    ) {
    }

    public static function activated(Subscription $subscription): self
    {
        return new self($subscription, true, null);
    }

    public static function requiresPayment(Subscription $subscription, InitiatedBill $bill): self
    {
        return new self($subscription, false, $bill);
    }
}
