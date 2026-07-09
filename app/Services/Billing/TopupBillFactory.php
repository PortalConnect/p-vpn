<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentService;

/**
 * Обёртка для обратной совместимости (используется cron-командами) —
 * реальная логика в PaymentService, провайдер берётся из config/payments.php.
 */
class TopupBillFactory
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function forSubscriptionPurchase(User $user, Subscription $subscription, int $amountKopecks): Payment
    {
        return $this->payments->subscriptionPurchase($user, $subscription, $amountKopecks);
    }
}
