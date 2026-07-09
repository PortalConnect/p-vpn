<?php

namespace PortalConnect\Subscriptions\Contracts;

use PortalConnect\Subscriptions\DTO\InitiatedBill;
use PortalConnect\Subscriptions\Models\Subscription;

/**
 * Мост к платёжному слою: пакет подписок не знает, кто и как выставляет счёт.
 * Приложение биндит реализацию (адаптер над своим PaymentService).
 */
interface PaymentInitiator
{
    public function initiateSubscriptionPurchase(object $user, Subscription $subscription, int $amountKopecks): InitiatedBill;
}
