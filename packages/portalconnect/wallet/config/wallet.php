<?php

return [
    'min_topup_rubles' => (int) env('WALLET_MIN_TOPUP_RUBLES', 100),
    'low_balance_threshold_kopecks' => (int) env('WALLET_LOW_BALANCE_THRESHOLD_KOPECKS', 20000),
    'allow_negative_balance' => env('WALLET_ALLOW_NEGATIVE_BALANCE', false),
    'grace_days' => 3,
];
