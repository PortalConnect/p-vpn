<?php

return [
    // Модель пользователя-владельца подписки.
    'user_model' => \App\Models\User::class,

    // Конкретный класс модели подписки (приложение подставляет наследника).
    'model' => \PortalConnect\Subscriptions\Models\Subscription::class,

    // Тарифы: месяцы => цена в копейках.
    'prices' => [
        1 => 20000,
        3 => 57000,
        6 => 108000,
    ],
];
