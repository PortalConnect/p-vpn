<?php

return [
    // Базовая валюта учёта: все суммы в БД — копейки RUB.
    'base' => 'RUB',

    // rate — сколько рублей стоит 1 единица валюты (для отображения).
    // Для боевых курсов подключить обновление по крону (ЦБ РФ/ECB) и писать сюда через cache.
    'supported' => [
        'RUB' => ['symbol' => '₽', 'rate' => 1, 'decimals' => 0],
        'USD' => ['symbol' => '$', 'rate' => env('CURRENCY_RATE_USD', 90), 'decimals' => 2],
        'EUR' => ['symbol' => '€', 'rate' => env('CURRENCY_RATE_EUR', 98), 'decimals' => 2],
    ],

    // Валюта по локали интерфейса — «переключил язык, увидел свою валюту».
    'locale_map' => [
        'ru' => 'RUB',
        'en' => 'USD',
    ],
];
