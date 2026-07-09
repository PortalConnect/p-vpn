<?php

use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Единый вебхук всех платёжных провайдеров.
Route::post('/webhooks/payment/{provider}', [PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payment');

// Legacy-URL Cardlink (настроен в кабинете шлюза) — тот же обработчик.
Route::post('/webhooks/cardlink', [PaymentWebhookController::class, 'handle'])
    ->defaults('provider', 'cardlink')
    ->name('webhooks.cardlink');
