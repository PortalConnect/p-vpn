<?php

namespace PortalConnect\Payments\Tests;

use PortalConnect\Payments\Gateways\Cardlink\CardlinkClient;
use PortalConnect\Payments\Gateways\Cardlink\DTO\PaymentStatus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CardlinkClientTest extends TestCase
{
    private CardlinkClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cardlink.api_url' => 'https://cardlink.test/api/v1',
            'cardlink.secret_key' => 'sk_test_123',
            'cardlink.shop_id' => 'shop_777',
            'cardlink.http_timeout_seconds' => 5,
            'cardlink.http_retry_count' => 0,
        ]);
        $this->client = $this->app->make(CardlinkClient::class);
    }

    public function test_create_bill_sends_authenticated_post_with_amount_in_rubles_and_returns_dto(): void
    {
        Http::fake([
            'cardlink.test/api/v1/bill/create' => Http::response([
                'bill_id' => 'bill_abc',
                'link' => 'https://cardlink.test/pay/bill_abc',
            ], 200),
        ]);

        $result = $this->client->createBill(20000, 'order-123', 'Подписка 1 мес');

        $this->assertSame('bill_abc', $result->billId);
        $this->assertSame('https://cardlink.test/pay/bill_abc', $result->payUrl);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://cardlink.test/api/v1/bill/create'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer sk_test_123')
                && $request['amount'] === 200.00
                && $request['order_id'] === 'order-123'
                && $request['description'] === 'Подписка 1 мес'
                && $request['type'] === 'ONE'
                && $request['shop_id'] === 'shop_777';
        });
    }

    public function test_create_bill_throws_on_non_2xx(): void
    {
        Http::fake([
            'cardlink.test/api/v1/bill/create' => Http::response(['error' => 'bad'], 422),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->client->createBill(20000, 'order-x', 'desc');
    }

    public function test_get_payment_status_returns_dto(): void
    {
        Http::fake([
            'cardlink.test/api/v1/payment/status*' => Http::response([
                'status' => 'SUCCESS',
                'payment_id' => 'pay_xyz',
                'amount' => 200.00,
            ], 200),
        ]);

        $status = $this->client->getPaymentStatus('pay_xyz');

        $this->assertSame(PaymentStatus::STATUS_SUCCESS, $status->status);
        $this->assertSame('pay_xyz', $status->paymentId);
        $this->assertSame(20000, $status->amountKopecks);
        $this->assertTrue($status->isSuccess());
    }

    public function test_deactivate_bill_sends_post(): void
    {
        Http::fake([
            'cardlink.test/api/v1/bill/toggle_activity' => Http::response(['ok' => true], 200),
        ]);

        $this->client->deactivateBill('bill_abc');

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/bill/toggle_activity')
                && $request['bill_id'] === 'bill_abc'
                && $request['active'] === false;
        });
    }
}
