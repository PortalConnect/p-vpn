<?php

return [
    // Провайдер по умолчанию — им выставляются новые счета.
    'default' => env('PAYMENT_PROVIDER', 'cardlink'),

    // Реестр провайдеров: ключ → класс. Новый шлюз = новый класс + строка здесь.
    'providers' => [
        'cardlink' => \App\Services\Payments\Providers\CardlinkProvider::class,
        'freekassa' => \App\Services\Payments\Providers\FreeKassaProvider::class,
    ],
];
