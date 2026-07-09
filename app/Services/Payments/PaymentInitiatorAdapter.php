<?php

namespace App\Services\Payments;

use PortalConnect\Subscriptions\Contracts\PaymentInitiator;
use PortalConnect\Subscriptions\DTO\InitiatedBill;
use PortalConnect\Subscriptions\Models\Subscription;

/** Мост пакета подписок к платёжному слою приложения. */
class PaymentInitiatorAdapter implements PaymentInitiator
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function initiateSubscriptionPurchase(object $user, Subscription $subscription, int $amountKopecks): InitiatedBill
    {
        $payment = $this->payments->subscriptionPurchase($user, $subscription, $amountKopecks);

        return new InitiatedBill($payment->pay_url, $payment->id);
    }
}
