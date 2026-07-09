<?php

namespace PortalConnect\Payments\Gateways\Cardlink\DTO;

class PaymentStatus
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_PROCESS = 'PROCESS';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_UNDERPAID = 'UNDERPAID';
    public const STATUS_OVERPAID = 'OVERPAID';
    public const STATUS_FAIL = 'FAIL';

    public function __construct(
        public readonly string $status,
        public readonly ?string $paymentId,
        public readonly ?int $amountKopecks,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS || $this->status === self::STATUS_OVERPAID;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: (string) ($data['status'] ?? ''),
            paymentId: isset($data['payment_id']) ? (string) $data['payment_id'] : null,
            amountKopecks: isset($data['amount']) ? (int) round(((float) $data['amount']) * 100) : null,
        );
    }
}
