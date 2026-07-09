<?php

namespace Tests\Feature;

use App\Jobs\CreateVpnKeyJob;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CardlinkWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $signatureKey = 'test-sig-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cardlink.signature_key' => $this->signatureKey]);
        Queue::fake();
    }

    public function test_webhook_with_topup_intent_credits_balance(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id,
            'cardlink_bill_id' => 'bill_topup_1',
            'amount_kopecks' => 50000,
            'status' => Payment::STATUS_PENDING,
            'intent' => Payment::INTENT_WALLET_TOPUP,
        ]);

        $body = json_encode([
            'bill_id' => 'bill_topup_1',
            'payment_id' => 'pay_777',
            'status' => 'SUCCESS',
            'amount' => 500.00,
            'order_id' => (string) $payment->id,
            'paid_at' => '2026-04-25T10:00:00Z',
        ]);

        $response = $this->postCardlink($body);

        $response->assertStatus(200);
        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertSame('pay_777', $payment->provider_operation_id);
        $this->assertSame(50000, $user->wallet->fresh()->balance_kopecks);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_TOPUP,
            'amount_kopecks' => 50000,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_webhook_with_subscription_purchase_intent_credits_then_activates(): void
    {
        $user = User::factory()->create();
        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
        ]);
        $payment = Payment::create([
            'user_id' => $user->id,
            'cardlink_bill_id' => 'bill_purchase_1',
            'amount_kopecks' => Pricing::priceFor(1),
            'status' => Payment::STATUS_PENDING,
            'intent' => Payment::INTENT_SUBSCRIPTION_PURCHASE,
            'intent_subscription_id' => $sub->id,
        ]);

        $body = json_encode([
            'bill_id' => 'bill_purchase_1',
            'payment_id' => 'pay_purchase',
            'status' => 'SUCCESS',
            'amount' => Pricing::priceFor(1) / 100,
            'order_id' => (string) $payment->id,
            'paid_at' => '2026-04-25T10:00:00Z',
        ]);

        $response = $this->postCardlink($body);
        $response->assertStatus(200);

        $sub->refresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks, 'topup + immediate debit = 0');
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => WalletTransaction::TYPE_TOPUP,
            'amount_kopecks' => Pricing::priceFor(1),
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => WalletTransaction::TYPE_SUBSCRIPTION_DEBIT,
            'amount_kopecks' => -Pricing::priceFor(1),
            'related_subscription_id' => $sub->id,
        ]);
        // ключ НЕ создаётся автоматически — юзер сам выберет локацию через /keys
        Queue::assertNotPushed(CreateVpnKeyJob::class);
    }

    public function test_duplicate_webhook_for_same_bill_is_idempotent(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id,
            'cardlink_bill_id' => 'bill_dup',
            'amount_kopecks' => 30000,
            'status' => Payment::STATUS_PENDING,
            'intent' => Payment::INTENT_WALLET_TOPUP,
        ]);
        $body = json_encode([
            'bill_id' => 'bill_dup',
            'payment_id' => 'pay_dup',
            'status' => 'SUCCESS',
            'amount' => 300.00,
            'order_id' => (string) $payment->id,
        ]);

        $first = $this->postCardlink($body);
        $second = $this->postCardlink($body);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertSame(30000, $user->wallet->fresh()->balance_kopecks);
        $this->assertSame(1, WalletTransaction::where('type', WalletTransaction::TYPE_TOPUP)->count());
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $body = json_encode(['bill_id' => 'whatever', 'status' => 'SUCCESS']);

        $response = $this->withHeaders([
            'X-Signature' => 'wrong-signature',
            'Content-Type' => 'application/json',
        ])->postJson('/webhooks/cardlink', json_decode($body, true));

        $response->assertStatus(403);
    }

    public function test_webhook_for_unknown_bill_returns_404(): void
    {
        $body = json_encode([
            'bill_id' => 'bill_unknown',
            'status' => 'SUCCESS',
            'amount' => 100.00,
        ]);
        $response = $this->postCardlink($body);
        $response->assertStatus(404);
    }

    public function test_failed_webhook_marks_payment_failed_and_does_not_credit(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id,
            'cardlink_bill_id' => 'bill_fail_1',
            'amount_kopecks' => 10000,
            'status' => Payment::STATUS_PENDING,
            'intent' => Payment::INTENT_WALLET_TOPUP,
        ]);

        $body = json_encode([
            'bill_id' => 'bill_fail_1',
            'payment_id' => 'pay_fail',
            'status' => 'FAIL',
            'amount' => 100.00,
            'order_id' => (string) $payment->id,
        ]);

        $response = $this->postCardlink($body);
        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAIL, $payment->status);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
    }

    private function postCardlink(string $body)
    {
        $signature = hash_hmac('sha256', $body, $this->signatureKey);

        return $this->call(
            'POST',
            '/webhooks/cardlink',
            [],
            [],
            [],
            [
                'HTTP_X_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $body
        );
    }
}
