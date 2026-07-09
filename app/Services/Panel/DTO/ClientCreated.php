<?php

namespace App\Services\Panel\DTO;

class ClientCreated
{
    public function __construct(
        public readonly int $id,
        public readonly string $config,
        public readonly string $qrCodeBase64,
    ) {
    }

    public static function fromArray(array $data): self
    {
        // Панель отдаёт {success: true, client: {...}} — данные вложены в client.
        $client = is_array($data['client'] ?? null) ? $data['client'] : $data;

        return new self(
            id: (int) ($client['id'] ?? 0),
            config: (string) ($client['config'] ?? ''),
            qrCodeBase64: (string) ($client['qr_code'] ?? ''),
        );
    }
}
