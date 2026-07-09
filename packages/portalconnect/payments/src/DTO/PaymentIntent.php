<?php

namespace PortalConnect\Payments\DTO;

/**
 * Провайдеро-независимое описание счёта: пакет не знает про Eloquent-модели
 * приложения — только то, что нужно шлюзу.
 */
class PaymentIntent
{
    public function __construct(
        public readonly string $orderId,
        public readonly int $amountKopecks,
        public readonly string $description,
    ) {
    }
}
