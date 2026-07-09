<?php

namespace PortalConnect\Payments\Providers;

use PortalConnect\Payments\Contracts\PaymentProvider;
use PortalConnect\Payments\DTO\CreatedBill;
use PortalConnect\Payments\DTO\PaymentIntent;
use PortalConnect\Payments\DTO\WebhookResult;
use PortalConnect\Payments\Exceptions\WebhookRejectedException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * FreeKassa (docs.freekassa.net), схема SCI:
 *  - счёт = платёжная ссылка на pay.fk.money с подписью md5(m:oa:secret1:currency:o);
 *  - уведомление: POST с SIGN = md5(MERCHANT_ID:AMOUNT:secret2:MERCHANT_ORDER_ID),
 *    в ответ шлюз ждёт строку "YES".
 */
class FreeKassaProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'freekassa';
    }

    public function createBill(PaymentIntent $intent): CreatedBill
    {
        $shopId = (string) config('freekassa.shop_id');
        $secret = (string) config('freekassa.secret1');
        $currency = (string) config('freekassa.currency', 'RUB');

        // Сумма в рублях с точкой — та же строка обязана попасть в подпись.
        $amount = number_format($intent->amountKopecks / 100, 2, '.', '');
        $orderId = $intent->orderId;

        $sign = md5("{$shopId}:{$amount}:{$secret}:{$currency}:{$orderId}");

        $query = http_build_query([
            'm' => $shopId,
            'oa' => $amount,
            'currency' => $currency,
            'o' => $orderId,
            's' => $sign,
            'lang' => 'ru',
        ]);

        $payUrl = rtrim((string) config('freekassa.pay_url', 'https://pay.fk.money/'), '?') . '?' . $query;

        // Внешнего id до оплаты у SCI нет — intid придёт в уведомлении.
        return new CreatedBill(null, $payUrl);
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        $this->assertTrustedIp($request);

        $merchantId = (string) $request->input('MERCHANT_ID', '');
        $amount = (string) $request->input('AMOUNT', '');
        $orderId = (string) $request->input('MERCHANT_ORDER_ID', '');
        $sign = (string) $request->input('SIGN', '');

        if ($merchantId === '' || $amount === '' || $orderId === '' || $sign === '') {
            throw new WebhookRejectedException('missing fields', 400);
        }

        $secret2 = (string) config('freekassa.secret2');
        $expected = md5("{$merchantId}:{$amount}:{$secret2}:{$orderId}");

        if (!hash_equals($expected, strtolower($sign))) {
            throw new WebhookRejectedException('bad signature');
        }

        // Уведомление FreeKassa приходит только по факту успешной оплаты.
        return new WebhookResult(
            success: true,
            orderId: (int) $orderId,
            externalId: null,
            operationId: (string) $request->input('intid', '') ?: null,
            amountKopecks: (int) round(((float) $amount) * 100),
            paidAt: now(),
            raw: $request->all(),
        );
    }

    public function successResponse(): Response
    {
        return response('YES', 200);
    }

    private function assertTrustedIp(Request $request): void
    {
        if (!config('freekassa.check_ip', true)) {
            return;
        }

        $allowed = (array) config('freekassa.allowed_ips', []);
        if ($allowed !== [] && !in_array($request->ip(), $allowed, true)) {
            throw new WebhookRejectedException('untrusted ip: ' . $request->ip());
        }
    }
}
