<?php

return [
    // Модель пользователя-владельца кошелька.
    'user_model' => \App\Models\User::class,

    // Конкретные классы моделей (приложение подставляет наследников).
    'model' => \PortalConnect\Wallet\Models\Wallet::class,
    'transaction_model' => \PortalConnect\Wallet\Models\WalletTransaction::class,

    'min_topup_rubles' => (int) env('WALLET_MIN_TOPUP_RUBLES', 100),
    'low_balance_threshold_kopecks' => (int) env('WALLET_LOW_BALANCE_THRESHOLD_KOPECKS', 20000),
    'allow_negative_balance' => env('WALLET_ALLOW_NEGATIVE_BALANCE', false),
    'grace_days' => 3,
];
