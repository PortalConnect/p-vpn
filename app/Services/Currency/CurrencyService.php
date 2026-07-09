<?php

namespace App\Services\Currency;

/**
 * Отображение сумм в валюте пользователя. Учёт (кошелёк, платежи) остаётся
 * в базовой валюте — конвертация только на витрине.
 */
class CurrencyService
{
    /** @return array{code:string,symbol:string,rate:float,decimals:int} */
    public function forLocale(string $locale): array
    {
        $code = (string) config("currencies.locale_map.{$locale}", config('currencies.base', 'RUB'));

        return $this->currency($code);
    }

    /** @return array{code:string,symbol:string,rate:float,decimals:int} */
    public function currency(string $code): array
    {
        $supported = (array) config('currencies.supported', []);
        $meta = $supported[$code] ?? $supported[config('currencies.base', 'RUB')] ?? ['symbol' => '₽', 'rate' => 1, 'decimals' => 0];

        return [
            'code' => $code,
            'symbol' => (string) $meta['symbol'],
            'rate' => (float) $meta['rate'],
            'decimals' => (int) $meta['decimals'],
        ];
    }
}
