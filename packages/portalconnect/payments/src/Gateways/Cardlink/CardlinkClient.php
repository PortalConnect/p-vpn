<?php

namespace PortalConnect\Payments\Gateways\Cardlink;

use PortalConnect\Payments\Gateways\Cardlink\DTO\BillResponse;
use PortalConnect\Payments\Gateways\Cardlink\DTO\PaymentStatus;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CardlinkClient
{
    public function createBill(int $amountKopecks, string $orderId, string $description): BillResponse
    {
        $response = $this->http()->post('/bill/create', [
            'amount' => $this->kopecksToRubles($amountKopecks),
            'order_id' => $orderId,
            'description' => $description,
            'type' => 'ONE',
            'shop_id' => config('cardlink.shop_id'),
        ]);

        if (!$response->successful()) {
            $this->logError('bill/create', $response->status(), $response->body());
            throw new RuntimeException("Cardlink bill/create failed: HTTP {$response->status()}");
        }

        return BillResponse::fromArray($response->json() ?? []);
    }

    public function getPaymentStatus(string $paymentId): PaymentStatus
    {
        $response = $this->http()->get('/payment/status', ['payment_id' => $paymentId]);

        if (!$response->successful()) {
            $this->logError('payment/status', $response->status(), $response->body());
            throw new RuntimeException("Cardlink payment/status failed: HTTP {$response->status()}");
        }

        return PaymentStatus::fromArray($response->json() ?? []);
    }

    public function deactivateBill(string $billId): void
    {
        $response = $this->http()->post('/bill/toggle_activity', [
            'bill_id' => $billId,
            'active' => false,
        ]);

        if (!$response->successful()) {
            $this->logError('bill/toggle_activity', $response->status(), $response->body());
            throw new RuntimeException("Cardlink bill/toggle_activity failed: HTTP {$response->status()}");
        }
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl(rtrim(config('cardlink.api_url'), '/'))
            ->withToken(config('cardlink.secret_key'))
            ->acceptJson()
            ->timeout((int) config('cardlink.http_timeout_seconds', 10));

        $retry = (int) config('cardlink.http_retry_count', 0);
        if ($retry > 0) {
            $request = $request->retry($retry, (int) config('cardlink.http_retry_delay_ms', 200));
        }

        return $request;
    }

    private function kopecksToRubles(int $kopecks): float
    {
        return round($kopecks / 100, 2);
    }

    private function logError(string $endpoint, int $status, string $body): void
    {
        Log::warning('cardlink request failed', [
            'endpoint' => $endpoint,
            'status' => $status,
            'body' => mb_substr($body, 0, 500),
        ]);
    }
}
