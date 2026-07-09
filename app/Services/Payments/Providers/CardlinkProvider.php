<?php

namespace App\Services\Payments\Providers;

use App\Models\Payment;
use App\Services\Cardlink\CardlinkClient;
use App\Services\Cardlink\DTO\WebhookPayload;
use App\Services\Cardlink\WebhookVerifier;
use App\Services\Payments\Contracts\PaymentProvider;
use App\Services\Payments\DTO\CreatedBill;
use App\Services\Payments\DTO\WebhookResult;
use App\Services\Payments\Exceptions\WebhookRejectedException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Адаптер над существующим Cardlink-кодом (CardlinkClient/WebhookVerifier
 * не менялись) — приводит его к общему контракту PaymentProvider.
 */
class CardlinkProvider implements PaymentProvider
{
    public function __construct(
        private CardlinkClient $client,
        private WebhookVerifier $verifier,
    ) {
    }

    public function key(): string
    {
        return 'cardlink';
    }

    public function createBill(Payment $payment, string $description): CreatedBill
    {
        $bill = $this->client->createBill(
            $payment->amount_kopecks,
            (string) $payment->id,
            $description
        );

        return new CreatedBill($bill->billId, $bill->payUrl);
    }

    public function parseWebhook(Request $request): WebhookResult
    {
        if (!$this->verifier->verify($request)) {
            throw new WebhookRejectedException('bad signature');
        }

        $payload = WebhookPayload::fromArray($request->json()->all());

        return new WebhookResult(
            success: $payload->isSuccess(),
            orderId: $payload->orderId !== null ? (int) $payload->orderId : null,
            externalId: $payload->billId,
            operationId: $payload->paymentId,
            amountKopecks: $payload->amountKopecks > 0 ? $payload->amountKopecks : null,
            paidAt: $payload->paidAt,
            raw: $payload->raw,
        );
    }

    public function successResponse(): Response
    {
        return response('ok', 200);
    }
}
