<?php

namespace PortalConnect\Subscriptions;

use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PortalConnect\Subscriptions\Models\Plan;

/**
 * Тарифы: источник истины — таблица subscription_plans; пока планов в БД нет,
 * работает fallback на config('subscriptions.prices') (месяцы => копейки).
 */
class Pricing
{
    public static function priceFor(int $months): int
    {
        $plan = self::planFor($months);
        if ($plan) {
            return $plan->price_kopecks;
        }

        $prices = (array) config('subscriptions.prices', []);
        if (!isset($prices[$months])) {
            throw new InvalidArgumentException("Unknown subscription period: {$months} months");
        }

        return $prices[$months];
    }

    /** @return array<int,int> months => price in kopecks */
    public static function all(): array
    {
        if (self::plansAvailable()) {
            $fromDb = Plan::query()->active()
                ->pluck('price_kopecks', 'months')
                ->map(fn ($v) => (int) $v)
                ->all();
            if ($fromDb !== []) {
                return $fromDb;
            }
        }

        return (array) config('subscriptions.prices', []);
    }

    public static function isValidPeriod(int $months): bool
    {
        return isset(self::all()[$months]);
    }

    public static function planFor(int $months): ?Plan
    {
        if (!self::plansAvailable()) {
            return null;
        }

        return Plan::query()->active()->where('months', $months)->first();
    }

    private static function plansAvailable(): bool
    {
        static $available = null;
        if ($available === null) {
            try {
                $available = Schema::hasTable('subscription_plans');
            } catch (\Throwable) {
                $available = false;
            }
        }

        return $available;
    }
}
