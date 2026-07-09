<?php

return [
    'base_url' => env('PANEL_BASE_URL', ''),

    // Статичный токен — ручной override. Если пуст, PanelClient сам логинится
    // по email/password и кеширует токен (панельные JWT живут 30 дней).
    'jwt_token' => env('PANEL_JWT_TOKEN', ''),
    'email' => env('PANEL_EMAIL', ''),
    'password' => env('PANEL_PASSWORD', ''),

    'default_server_id' => env('PANEL_DEFAULT_SERVER_ID') ? (int) env('PANEL_DEFAULT_SERVER_ID') : null,
    'http_timeout_seconds' => 30,
];
