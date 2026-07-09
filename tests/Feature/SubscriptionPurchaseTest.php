<?php

namespace Tests\Feature;

use App\Jobs\CreateVpnKeyJob;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;
use PortalConnect\Subscriptions\Pricing;
use PortalConnect\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cardlink.api_url' => 'https://cardlink.test/api/v1',
            'cardlink.secret_key' => 'sk',
            'cardlink.shop_id' => 'shop',
        ]);
        Queue::fake();
    }

    public function test_purchase_with_sufficient_balance_activates_immediately(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->app->make(WalletService::class)->credit(
            $user->wallet,
            Pricing::priceFor(1),
            WalletTransaction::TYPE_TOPUP
        );

        $response = $this->actingAs($user)->post('/subscriptions', ['months' => 1]);

        $response->assertRedirect('/dashboard');
        $sub = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
        // ключ НЕ создаётся автоматически — юзер сам выберет локацию через /keys
        Queue::assertNotPushed(CreateVpnKeyJob::class);
        Http::assertNothingSent();
    }

    public function test_purchase_with_zero_balance_creates_pending_payment_and_redirects_to_cardlink(): void
    {
        Http::fake([
            'cardlink.test/*' => Http::response([
                'bill_id' => 'bill_purchase_x',
                'link' => 'https://cardlink.test/pay/xyz',
            ], 200),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post('/subscriptions', ['months' => 1]);

        $response->assertRedirect('https://cardlink.test/pay/xyz');

        $sub = Subscription::where('user_id', $user->id)->first();
        $this->assertSame(Subscription::STATUS_PENDING, $sub->status);

        $payment = Payment::where('user_id', $user->id)->first();
        $this->assertSame(Payment::INTENT_SUBSCRIPTION_PURCHASE, $payment->intent);
        $this->assertSame($sub->id, $payment->intent_subscription_id);
        $this->assertSame(Pricing::priceFor(1), $payment->amount_kopecks);
        Queue::assertNothingPushed();
    }

    public function test_purchase_rejects_unknown_period(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post('/subscriptions', ['months' => 2])
            ->assertSessionHasErrors('months');
        $this->assertSame(0, Subscription::count());
    }
}
