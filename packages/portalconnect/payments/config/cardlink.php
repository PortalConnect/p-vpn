<?php

return [
    'api_url' => env('CARDLINK_API_URL', 'https://cardlink.link/api/v1'),
    'secret_key' => env('CARDLINK_SECRET_KEY', ''),
    'signature_key' => env('CARDLINK_SIGNATURE_KEY', ''),
    'shop_id' => env('CARDLINK_SHOP_ID', ''),
    'sandbox' => env('CARDLINK_SANDBOX', false),
    'min_topup_kopecks' => (int) env('CARDLINK_MIN_TOPUP_KOPECKS', 10000),
    'http_timeout_seconds' => 10,
    'http_retry_count' => 2,
    'http_retry_delay_ms' => 200,
];
