<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WalletTransaction;
use App\Services\Payments\DTO\WebhookResult;
use App\Services\Payments\Exceptions\WebhookRejectedException;
use App\Services\Subscriptions\SubscriptionManager;
use App\Services\Wallet\Exceptions\InsufficientFundsException;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Общая (провайдеро-независимая) обработка подтверждённого вебхука.
 *
 * Гарантии:
 *  - идемпотентность: строка платежа лочится (SELECT ... FOR UPDATE), повторный
 *    вебхук по уже успешному платежу — no-op; дополнительный предохранитель —
 *    проверка существующей wallet-транзакции по related_payment_id;
 *  - консистентность: смена статуса, зачисление кошелька и активация подписки
 *    происходят в одной БД-транзакции;
 *  - защита от подмены суммы: если провайдер сообщил сумму и она меньше
 *    ожидаемой — вебхук отклоняется.
 */
class PaymentFulfillmentService
{
    public const RESULT_ALREADY = 'already_processed';
    public const RESULT_FAILED = 'fail_recorded';
    public const RESULT_SUCCESS = 'success';

    public function __construct(
        private WalletService $wallet,
        private SubscriptionManager $subscriptions,
    ) {
    }

    public function apply(Payment $payment, WebhookResult $result): string
    {
        return DB::transaction(function () use ($payment, $result) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === Payment::STATUS_SUCCESS) {
                return self::RESULT_ALREADY;
            }

            if ($result->amountKopecks !== null && $result->amountKopecks < $payment->amount_kopecks) {
                Log::warning('payment webhook: amount mismatch', [
                    'payment_id' => $payment->id,
                    'expected' => $payment->amount_kopecks,
                    'received' => $result->amountKopecks,
                ]);
                throw new WebhookRejectedException('amount mismatch', 400);
            }

            if (!$result->success) {
                $payment->update([
                    'status' => Payment::STATUS_FAIL,
                    'provider_operation_id' => $result->operationId,
                    'paid_at' => $result->paidAt,
                    'raw_payload' => $result->raw,
                ]);
                return self::RESULT_FAILED;
            }

            $payment->update([
                'status' => Payment::STATUS_SUCCESS,
                'external_id' => $payment->external_id ?? $result->externalId,
                'provider_operation_id' => $result->operationId,
                'paid_at' => $result->paidAt ?? now(),
                'raw_payload' => $result->raw,
            ]);

            $this->creditOnce($payment);
            $this->activateIntentSubscription($payment);

            return self::RESULT_SUCCESS;
        });
    }

    private function creditOnce(Payment $payment): void
    {
        $alreadyCredited = WalletTransaction::query()
            ->where('related_payment_id', $payment->id)
            ->where('type', WalletTransaction::TYPE_TOPUP)
            ->exists();

        if ($alreadyCredited) {
            Log::warning('payment webhook: topup already credited, skipping', [
                'payment_id' => $payment->id,
            ]);
            return;
        }

        $this->wallet->credit(
            $payment->user->wallet,
            $payment->amount_kopecks,
            WalletTransaction::TYPE_TOPUP,
            ['related_payment_id' => $payment->id]
        );
    }

    private function activateIntentSubscription(Payment $payment): void
    {
        if ($payment->intent !== Payment::INTENT_SUBSCRIPTION_PURCHASE || !$payment->intent_subscription_id) {
            return;
        }

        $sub = Subscription::find($payment->intent_subscription_id);
        if (!$sub || $sub->status !== Subscription::STATUS_PENDING) {
            return;
        }

        try {
            $this->subscriptions->activate($sub);
        } catch (InsufficientFundsException $e) {
            // Гонка: баланс могли потратить между зачислением и активацией.
            // Платёж зачислен, подписка останется pending — юзер активирует с баланса.
            Log::warning('payment webhook: race on subscription activation', [
                'subscription_id' => $sub->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
