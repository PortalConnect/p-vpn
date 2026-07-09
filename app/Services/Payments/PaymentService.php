<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\DTO\WebhookResult;

/**
 * Единая точка создания платежей: заводит Payment, выставляет счёт
 * у дефолтного провайдера и сохраняет ссылку на оплату.
 */
class PaymentService
{
    public function __construct(private PaymentManager $manager)
    {
    }

    /** Найти платёж, к которому относится вебхук провайдера. */
    public function findForWebhook(string $provider, WebhookResult $result): ?Payment
    {
        if ($result->orderId !== null) {
            $payment = Payment::find($result->orderId);
            // Провайдер должен совпадать (legacy-строки без provider допускаем).
            if ($payment && ($payment->provider === null || $payment->provider === $provider)) {
                return $payment;
            }
            return null;
        }

        if ($result->externalId !== null) {
            return Payment::query()
                ->where('external_id', $result->externalId)
                ->where(fn ($q) => $q->where('provider', $provider)->orWhereNull('provider'))
                ->first()
                // Legacy: старые cardlink-платежи до миграции на external_id.
                ?? Payment::query()->where('cardlink_bill_id', $result->externalId)->first();
        }

        return null;
    }

    public function walletTopup(User $user, int $amountKopecks): Payment
    {
        return $this->create(
            $user,
            $amountKopecks,
            Payment::INTENT_WALLET_TOPUP,
            null,
            "Пополнение баланса ({$user->email})"
        );
    }

    public function subscriptionPurchase(User $user, Subscription $subscription, int $amountKopecks): Payment
    {
        return $this->create(
            $user,
            $amountKopecks,
            Payment::INTENT_SUBSCRIPTION_PURCHASE,
            $subscription->id,
            "Оплата подписки на {$subscription->months} мес ({$user->email})"
        );
    }

    private function create(
        User $user,
        int $amountKopecks,
        string $intent,
        ?int $subscriptionId,
        string $description
    ): Payment {
        $provider = $this->manager->default();

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount_kopecks' => $amountKopecks,
            'status' => Payment::STATUS_PENDING,
            'intent' => $intent,
            'intent_subscription_id' => $subscriptionId,
            'provider' => $provider->key(),
        ]);

        $bill = $provider->createBill($payment, $description);

        $payment->update([
            'external_id' => $bill->externalId,
            'pay_url' => $bill->payUrl,
            // Legacy-колонка: пока её читает старый код/выгрузки — дублируем для cardlink.
            'cardlink_bill_id' => $provider->key() === 'cardlink' ? $bill->externalId : null,
        ]);

        return $payment->fresh();
    }
}
