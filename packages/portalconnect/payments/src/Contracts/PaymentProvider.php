<?php

namespace PortalConnect\Payments\Contracts;

use PortalConnect\Payments\DTO\CreatedBill;
use PortalConnect\Payments\DTO\PaymentIntent;
use PortalConnect\Payments\DTO\WebhookResult;
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
    public function createBill(PaymentIntent $intent): CreatedBill;

    /**
     * Проверить подпись вебхука и привести его к общему виду.
     *
     * @throws \PortalConnect\Payments\Exceptions\WebhookRejectedException при невалидной подписи/запросе
     */
    public function parseWebhook(Request $request): WebhookResult;

    /** Ответ, который шлюз считает подтверждением обработки (FreeKassa требует "YES"). */
    public function successResponse(): Response;
}
