<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeKassaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(User $user, int $kopecks = 15000): Payment
    {
        Wallet::firstOrCreate(['user_id' => $user->id], ['balance_kopecks' => 0, 'currency' => 'RUB']);

        return Payment::create([
            'user_id' => $user->id,
            'provider' => 'freekassa',
            'amount_kopecks' => $kopecks,
            'status' => Payment::STATUS_PENDING,
            'intent' => Payment::INTENT_WALLET_TOPUP,
        ]);
    }

    private function sign(string $merchantId, string $amount, string $orderId): string
    {
        return md5("{$merchantId}:{$amount}:" . config('freekassa.secret2') . ":{$orderId}");
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'freekassa.shop_id' => '12345',
            'freekassa.secret1' => 's1',
            'freekassa.secret2' => 's2',
            'freekassa.check_ip' => false,
        ]);
    }

    public function test_valid_webhook_credits_wallet_and_answers_yes(): void
    {
        $user = User::factory()->create();
        $payment = $this->makePayment($user);

        $response = $this->post('/webhooks/payment/freekassa', [
            'MERCHANT_ID' => '12345',
            'AMOUNT' => '150.00',
            'intid' => 'fk-op-1',
            'MERCHANT_ORDER_ID' => (string) $payment->id,
            'SIGN' => $this->sign('12345', '150.00', (string) $payment->id),
        ]);

        $response->assertStatus(200);
        $this->assertSame('YES', $response->getContent());

        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertSame('fk-op-1', $payment->provider_operation_id);
        $this->assertSame(15000, $user->wallet->fresh()->balance_kopecks);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $user = User::factory()->create();
        $payment = $this->makePayment($user);

        $params = [
            'MERCHANT_ID' => '12345',
            'AMOUNT' => '150.00',
            'intid' => 'fk-op-2',
            'MERCHANT_ORDER_ID' => (string) $payment->id,
            'SIGN' => $this->sign('12345', '150.00', (string) $payment->id),
        ];

        $this->post('/webhooks/payment/freekassa', $params)->assertStatus(200);
        $this->post('/webhooks/payment/freekassa', $params)->assertStatus(200);

        $this->assertSame(15000, $user->wallet->fresh()->balance_kopecks);
        $this->assertSame(1, WalletTransaction::where('related_payment_id', $payment->id)->count());
    }

    public function test_bad_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        $payment = $this->makePayment($user);

        $this->post('/webhooks/payment/freekassa', [
            'MERCHANT_ID' => '12345',
            'AMOUNT' => '150.00',
            'intid' => 'fk-op-3',
            'MERCHANT_ORDER_ID' => (string) $payment->id,
            'SIGN' => 'wrong',
        ])->assertStatus(403);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
    }

    public function test_underpaid_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $payment = $this->makePayment($user, 15000);

        $this->post('/webhooks/payment/freekassa', [
            'MERCHANT_ID' => '12345',
            'AMOUNT' => '10.00',
            'intid' => 'fk-op-4',
            'MERCHANT_ORDER_ID' => (string) $payment->id,
            'SIGN' => $this->sign('12345', '10.00', (string) $payment->id),
        ])->assertStatus(400);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
    }

    public function test_unknown_payment_returns_404(): void
    {
        $this->post('/webhooks/payment/freekassa', [
            'MERCHANT_ID' => '12345',
            'AMOUNT' => '150.00',
            'intid' => 'fk-op-5',
            'MERCHANT_ORDER_ID' => '999999',
            'SIGN' => $this->sign('12345', '150.00', '999999'),
        ])->assertStatus(404);
    }
}
