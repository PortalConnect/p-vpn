<?php

namespace PortalConnect\Subscriptions;

use Illuminate\Support\Facades\DB;
use PortalConnect\Subscriptions\Contracts\PaymentInitiator;
use PortalConnect\Subscriptions\DTO\PurchaseOutcome;
use PortalConnect\Subscriptions\Events\SubscriptionActivated;
use PortalConnect\Subscriptions\Models\Plan;
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
     * Принимает период в месяцах или конкретный Plan; у плана с trial_days
     * первая подписка тега активируется триалом без списания.
     */
    public function purchase(object $user, int|Plan $plan, string $tag = 'main'): PurchaseOutcome
    {
        $planModel = $plan instanceof Plan ? $plan : Pricing::planFor($plan);
        $months = $plan instanceof Plan ? $plan->months : $plan;
        $price = $planModel?->price_kopecks ?? Pricing::priceFor($months);

        $model = config('subscriptions.model', Subscription::class);
        $subscription = DB::transaction(fn () => $model::create([
            'user_id' => $user->id,
            'plan_id' => $planModel?->id,
            'tag' => $tag,
            'status' => Subscription::STATUS_PENDING,
            'months' => $months,
            'price_kopecks' => $price,
        ]));

        // Триал: только первая подписка этого тега, деньги не списываются.
        if ($planModel?->hasTrial() && !$this->hadAnySubscription($user, $tag, $subscription->id)) {
            $this->activateTrial($subscription, $planModel);
            return PurchaseOutcome::activated($subscription);
        }

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

    /**
     * Отмена: immediately=true — статус cancelled и доступ закрывается сейчас
     * (ends_at обрезается); иначе подписка доработает период, автопродление
     * по ней не сработает (статус cancelled).
     */
    public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    {
        $subscription->forceFill([
            'status' => Subscription::STATUS_CANCELLED,
            'canceled_at' => now(),
            'ends_at' => $immediately ? now() : $subscription->ends_at,
        ])->save();

        return $subscription->fresh();
    }

    /** Ручное продление: тот же план/длительность, оплата с кошелька или счётом. */
    public function renew(Subscription $subscription): DTO\PurchaseOutcome
    {
        return $this->purchase(
            $subscription->user,
            $subscription->plan ?? $subscription->months,
            $subscription->tag ?? 'main'
        );
    }

    /**
     * Смена плана: неиспользованный остаток текущей подписки возвращается
     * на кошелёк pro-rata, текущая отменяется, новый план покупается
     * (с только что зачисленного остатка + баланса, либо счётом).
     */
    public function changePlan(Subscription $subscription, Plan $newPlan): PurchaseOutcome
    {
        if ($subscription->active()) {
            $totalDays = max(1, (int) $subscription->starts_at->diffInDays($subscription->ends_at));
            $unusedDays = max(0, (int) now()->diffInDays($subscription->ends_at, false));
            $refund = intdiv($subscription->price_kopecks * $unusedDays, $totalDays);

            if ($refund > 0 && !$subscription->onTrial()) {
                $this->wallet->refund($subscription->user->wallet, $refund, [
                    'related_subscription_id' => $subscription->id,
                    'description' => 'Возврат остатка при смене плана',
                ]);
            }
        }

        $this->cancel($subscription, immediately: true);

        return $this->purchase($subscription->user->fresh(), $newPlan, $subscription->tag ?? 'main');
    }

    private function hadAnySubscription(object $user, string $tag, int $exceptId): bool
    {
        $model = config('subscriptions.model', Subscription::class);

        return $model::query()
            ->where('user_id', $user->id)
            ->where('tag', $tag)
            ->where('id', '!=', $exceptId)
            ->exists();
    }

    /** Активация триала: без списания, срок = trial_days; signup fee — списывается, если задана. */
    private function activateTrial(Subscription $subscription, Plan $plan): void
    {
        DB::transaction(function () use ($subscription, $plan) {
            if ($plan->signup_fee_kopecks > 0) {
                $this->wallet->debit(
                    $subscription->user->wallet,
                    $plan->signup_fee_kopecks,
                    WalletTransaction::TYPE_SUBSCRIPTION_DEBIT,
                    ['related_subscription_id' => $subscription->id, 'description' => 'Signup fee']
                );
            }

            $end = now()->addDays($plan->trial_days);
            $subscription->forceFill([
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now(),
                'ends_at' => $end,
                'trial_ends_at' => $end,
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
            'plan_id' => Pricing::planFor($expiring->months)?->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => $expiring->months,
            'price_kopecks' => Pricing::priceFor($expiring->months),
            'auto_renewed_from_id' => $expiring->id,
        ]);

        $this->activate($new);

        return $new->fresh();
    }
}
