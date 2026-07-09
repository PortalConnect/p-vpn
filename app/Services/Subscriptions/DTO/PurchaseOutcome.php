<?php

namespace App\Services\Subscriptions\DTO;

use App\Models\Payment;
use App\Models\Subscription;

/**
 * Результат покупки подписки: либо активирована с баланса,
 * либо создан платёж и нужен редирект на оплату.
 */
class PurchaseOutcome
{
    private function __construct(
        public readonly Subscription $subscription,
        public readonly bool $activated,
        public readonly ?Payment $payment,
    ) {
    }

    public static function activated(Subscription $subscription): self
    {
        return new self($subscription, true, null);
    }

    public static function requiresPayment(Subscription $subscription, Payment $payment): self
    {
        return new self($subscription, false, $payment);
    }
}
