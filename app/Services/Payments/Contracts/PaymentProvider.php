<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use App\Services\Payments\DTO\CreatedBill;
use App\Services\Payments\DTO\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Контракт платёжного провайдера.
 *
 * Новый шлюз = новый класс с этим интерфейсом + строка в config/payments.php.
 * Существующий код (контроллеры, fulfillment, вебхук-роут) не меняется.
 */
interface PaymentProvider
{
    /** Ключ провайдера — как в config/payments.php ('cardlink', 'freekassa', ...). */
    public function key(): string;

    /** Выставить счёт: вернуть URL оплаты и внешний id (если шлюз его выдаёт до оплаты). */
    public function createBill(Payment $payment, string $description): CreatedBill;

    /**
     * Проверить подпись вебхука и привести его к общему виду.
     *
     * @throws \App\Services\Payments\Exceptions\WebhookRejectedException при невалидной подписи/запросе
     */
    public function parseWebhook(Request $request): WebhookResult;

    /** Ответ, который шлюз считает подтверждением обработки (FreeKassa требует "YES"). */
    public function successResponse(): Response;
}
