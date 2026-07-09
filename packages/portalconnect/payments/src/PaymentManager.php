<?php

namespace PortalConnect\Payments;

use PortalConnect\Payments\Contracts\PaymentProvider;
use InvalidArgumentException;

/**
 * Резолвит провайдеров из config/payments.php.
 * Добавление шлюза — только новый класс + запись в конфиге.
 */
class PaymentManager
{
    /** @var array<string, PaymentProvider> */
    private array $resolved = [];

    public function default(): PaymentProvider
    {
        return $this->provider((string) config('payments.default'));
    }

    public function provider(string $key): PaymentProvider
    {
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $class = config("payments.providers.{$key}");
        if (!is_string($class) || !class_exists($class)) {
            throw new InvalidArgumentException("Unknown payment provider [{$key}]");
        }

        $provider = app($class);
        if (!$provider instanceof PaymentProvider) {
            throw new InvalidArgumentException("Provider [{$key}] must implement PaymentProvider");
        }

        return $this->resolved[$key] = $provider;
    }

    public function has(string $key): bool
    {
        return is_string(config("payments.providers.{$key}"));
    }
}
