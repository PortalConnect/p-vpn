<?php

return [
    'shop_id' => env('FREEKASSA_SHOP_ID', ''),
    'secret1' => env('FREEKASSA_SECRET1', ''),   // подпись платёжной ссылки
    'secret2' => env('FREEKASSA_SECRET2', ''),   // подпись уведомлений
    'currency' => env('FREEKASSA_CURRENCY', 'RUB'),
    'pay_url' => env('FREEKASSA_PAY_URL', 'https://pay.fk.money/'),

    // Проверка IP уведомлений (для локальной симуляции можно выключить).
    'check_ip' => env('FREEKASSA_CHECK_IP', true),
    'allowed_ips' => [
        '168.119.157.136',
        '168.119.60.227',
        '178.154.197.79',
        '51.250.54.238',
    ],
];
