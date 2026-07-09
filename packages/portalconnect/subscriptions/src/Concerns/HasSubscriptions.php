<?php

namespace PortalConnect\Subscriptions\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use PortalConnect\Subscriptions\DTO\PurchaseOutcome;
use PortalConnect\Subscriptions\Models\Plan;
use PortalConnect\Subscriptions\Models\Subscription;
use PortalConnect\Subscriptions\SubscriptionManager;

/**
 * API подписок на модели пользователя (аналог HasPlanSubscriptions
 * из laravelcm/laravel-subscriptions, адаптированный под кошелёк):
 *
 *   $user->newSubscription(3);          // купить/выставить счёт на 3 мес
 *   $user->activeSubscription();        // текущая активная
 *   $user->hasActiveSubscription();     // bool
 *   $user->subscribedFor(3);            // активна ли подписка на период
 *   $user->onGrace();                   // истёкшая, но в grace-периоде
 */
trait HasSubscriptions
{
    public function subscriptions(): HasMany
    {
        return $this->hasMany(config('subscriptions.model', Subscription::class));
    }

    /** Купить подписку (период в месяцах или Plan): активация с баланса или счёт. */
    public function newSubscription(int|Plan $plan, string $tag = 'main'): PurchaseOutcome
    {
        return app(SubscriptionManager::class)->purchase($this, $plan, $tag);
    }

    /** Активная подписка тега (по умолчанию main). */
    public function subscription(string $tag = 'main'): ?Subscription
    {
        return $this->subscriptions()
            ->where('tag', $tag)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->orderByDesc('ends_at')
            ->first();
    }

    /** Подписан ли на конкретный план (id или slug). */
    public function subscribedTo(int|string $plan): bool
    {
        $planId = is_int($plan)
            ? $plan
            : Plan::query()->where('slug', $plan)->value('id');

        if (!$planId) {
            return false;
        }

        return $this->subscriptions()
            ->where('plan_id', $planId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->exists();
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->orderByDesc('ends_at')
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->exists();
    }

    /** Есть ли активная подписка ровно на этот период (в месяцах). */
    public function subscribedFor(int $months): bool
    {
        return $this->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->where('months', $months)
            ->exists();
    }

    /** Последняя активная или истёкшая — для стыковки продления и grace-логики. */
    public function lastActiveOrExpired(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_EXPIRED])
            ->orderByDesc('ends_at')
            ->first();
    }

    /** Истёкшая подписка в пределах grace-периода (ключи ещё не отозваны). */
    public function onGrace(): bool
    {
        $graceDays = (int) config('wallet.grace_days', 3);

        return $this->subscriptions()
            ->where('status', Subscription::STATUS_EXPIRED)
            ->where('ends_at', '>', now()->subDays($graceDays))
            ->exists();
    }
}
