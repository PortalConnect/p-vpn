<?php

namespace PortalConnect\Payments\Gateways\Cardlink\DTO;

use Illuminate\Support\Carbon;

class WebhookPayload
{
    public function __construct(
        public readonly string $billId,
        public readonly ?string $paymentId,
        public readonly string $status,
        public readonly ?string $orderId,
        public readonly int $amountKopecks,
        public readonly ?Carbon $paidAt,
        public readonly array $raw,
    ) {
    }

    public function isSuccess(): bool
    {
        return in_array($this->status, ['SUCCESS', 'OVERPAID'], true);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            billId: (string) ($data['bill_id'] ?? ''),
            paymentId: isset($data['payment_id']) ? (string) $data['payment_id'] : null,
            status: (string) ($data['status'] ?? ''),
            orderId: isset($data['order_id']) ? (string) $data['order_id'] : null,
            amountKopecks: isset($data['amount']) ? (int) round(((float) $data['amount']) * 100) : 0,
            paidAt: !empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            raw: $data,
        );
    }
}
