<?php

return [
    // Провайдер по умолчанию — им выставляются новые счета.
    'default' => env('PAYMENT_PROVIDER', 'cardlink'),

    // Реестр провайдеров: ключ → класс. Новый шлюз = новый класс + строка здесь.
    'providers' => [
        'cardlink' => \PortalConnect\Payments\Providers\CardlinkProvider::class,
        'freekassa' => \PortalConnect\Payments\Providers\FreeKassaProvider::class,
    ],
];
