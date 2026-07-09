<?php

namespace PortalConnect\Payments\Providers;

use PortalConnect\Payments\Gateways\Cardlink\CardlinkClient;
use PortalConnect\Payments\Gateways\Cardlink\DTO\WebhookPayload;
use PortalConnect\Payments\Gateways\Cardlink\WebhookVerifier;
use PortalConnect\Payments\Contracts\PaymentProvider;
use PortalConnect\Payments\DTO\CreatedBill;
use PortalConnect\Payments\DTO\PaymentIntent;
use PortalConnect\Payments\DTO\WebhookResult;
use PortalConnect\Payments\Exceptions\WebhookRejectedException;
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

    public function createBill(PaymentIntent $intent): CreatedBill
    {
        $bill = $this->client->createBill(
            $intent->amountKopecks,
            $intent->orderId,
            $intent->description
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
