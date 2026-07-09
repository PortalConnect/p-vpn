<?php

namespace PortalConnect\Subscriptions;

use Illuminate\Support\Facades\DB;
use PortalConnect\Subscriptions\Contracts\PaymentInitiator;
use PortalConnect\Subscriptions\DTO\PurchaseOutcome;
use PortalConnect\Subscriptions\Events\SubscriptionActivated;
use PortalConnect\Subscriptions\Models\Subscription;
use PortalConnect\Wallet\Models\WalletTransaction;
use PortalConnect\Wallet\WalletService;

/**
 * Жизненный цикл подписки: покупка, активация (списание с кошелька),
 * автопродление. Побочные эффекты приложения — через событие SubscriptionActivated.
 */
class SubscriptionManager
{
    public function __construct(
        private WalletService $wallet,
        private PaymentInitiator $payments,
    ) {
    }

    /** Хватает ли на балансе на подписку на N месяцев. */
    public function sufficientFor(object $user, int $months): bool
    {
        return $this->shortfall($user, $months) === 0;
    }

    /** Сколько не хватает до цены подписки (0 — хватает). */
    public function shortfall(object $user, int $months): int
    {
        $price = Pricing::priceFor($months);
        $balance = $user->wallet?->balance_kopecks ?? 0;

        return max(0, $price - $balance);
    }

    /**
     * Покупка: хватает баланса — активируем сразу; нет — pending-подписка + счёт.
     */
    public function purchase(object $user, int $months): PurchaseOutcome
    {
        $price = Pricing::priceFor($months);

        $model = config('subscriptions.model', Subscription::class);
        $subscription = DB::transaction(fn () => $model::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => $months,
            'price_kopecks' => $price,
        ]));

        if ($this->sufficientFor($user, $months)) {
            $this->activate($subscription);
            return PurchaseOutcome::activated($subscription);
        }

        $topupAmount = max(
            $this->shortfall($user, $months),
            (int) config('wallet.min_topup_rubles') * 100
        );

        $bill = $this->payments->initiateSubscriptionPurchase($user, $subscription, $topupAmount);

        return PurchaseOutcome::requiresPayment($subscription, $bill);
    }

    public function activate(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $user = $subscription->user;

            $tx = $this->wallet->debit(
                $user->wallet,
                $subscription->price_kopecks,
                WalletTransaction::TYPE_SUBSCRIPTION_DEBIT,
                ['related_subscription_id' => $subscription->id]
            );

            // Продление стыкуется к концу текущего периода, а не к "сейчас".
            $start = now();
            $previous = method_exists($user, 'lastActiveOrExpired') ? $user->lastActiveOrExpired() : null;
            if ($previous && $previous->ends_at && $previous->ends_at->isFuture()) {
                $start = $previous->ends_at;
            }

            $subscription->forceFill([
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMonths($subscription->months),
                'paid_via_transaction_id' => $tx->id,
            ])->save();
        });

        SubscriptionActivated::dispatch($subscription);
    }

    public function autoRenewIfPossible(Subscription $expiring): ?Subscription
    {
        $user = $expiring->user;
        if (!$user->wallet->auto_renew) {
            return null;
        }
        if (!$this->sufficientFor($user, $expiring->months)) {
            return null;
        }

        $model = config('subscriptions.model', Subscription::class);
        $new = $model::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => $expiring->months,
            'price_kopecks' => Pricing::priceFor($expiring->months),
            'auto_renewed_from_id' => $expiring->id,
        ]);

        $this->activate($new);

        return $new->fresh();
    }
}
