<?php

namespace PortalConnect\Payments\DTO;

use Carbon\Carbon;

/**
 * Унифицированный результат разбора вебхука любого провайдера.
 * Платёж ищется по orderId (наш payments.id), иначе по (provider, externalId).
 */
class WebhookResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $orderId,
        public readonly ?string $externalId,
        public readonly ?string $operationId,
        public readonly ?int $amountKopecks,
        public readonly ?Carbon $paidAt,
        public readonly array $raw,
    ) {
    }
}
