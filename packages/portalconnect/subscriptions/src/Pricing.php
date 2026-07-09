<?php

namespace PortalConnect\Subscriptions;

use InvalidArgumentException;

/** Тарифы читаются из config/subscriptions.php — приложение переопределяет publish'ем. */
class Pricing
{
    public static function priceFor(int $months): int
    {
        $prices = self::all();
        if (!isset($prices[$months])) {
            throw new InvalidArgumentException("Unknown subscription period: {$months} months");
        }

        return $prices[$months];
    }

    /** @return array<int,int> months => price in kopecks */
    public static function all(): array
    {
        return (array) config('subscriptions.prices', []);
    }

    public static function isValidPeriod(int $months): bool
    {
        return isset(self::all()[$months]);
    }
}
