<?php

namespace PortalConnect\Subscriptions\Tests;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PortalConnect\Subscriptions\Contracts\PaymentInitiator;
use PortalConnect\Subscriptions\DTO\InitiatedBill;
use PortalConnect\Subscriptions\Events\SubscriptionActivated;
use PortalConnect\Subscriptions\Models\Subscription;
use PortalConnect\Subscriptions\Pricing;
use PortalConnect\Subscriptions\SubscriptionManager;
use PortalConnect\Wallet\WalletService;
use Tests\TestCase;

class SubscriptionManagerTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): SubscriptionManager
    {
        return app(SubscriptionManager::class);
    }

    public function test_shortfall_and_sufficiency_follow_balance(): void
    {
        $user = User::factory()->create();
        $price = Pricing::priceFor(1);

        $this->assertSame($price, $this->manager()->shortfall($user, 1));
        $this->assertFalse($this->manager()->sufficientFor($user, 1));

        app(WalletService::class)->credit($user->wallet, $price, WalletTransaction::TYPE_TOPUP);
        $user->refresh();

        $this->assertSame(0, $this->manager()->shortfall($user, 1));
        $this->assertTrue($this->manager()->sufficientFor($user, 1));
    }

    public function test_purchase_with_balance_activates_and_fires_event(): void
    {
        Event::fake([SubscriptionActivated::class]);

        $user = User::factory()->create();
        app(WalletService::class)->credit($user->wallet, Pricing::priceFor(1), WalletTransaction::TYPE_TOPUP);
        $user->refresh();

        $outcome = $this->manager()->purchase($user, 1);

        $this->assertTrue($outcome->activated);
        $this->assertSame(Subscription::STATUS_ACTIVE, $outcome->subscription->fresh()->status);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
        Event::assertDispatched(SubscriptionActivated::class);
    }

    public function test_purchase_without_balance_initiates_bill(): void
    {
        $this->app->bind(PaymentInitiator::class, fn () => new class implements PaymentInitiator {
            public function initiateSubscriptionPurchase(object $user, Subscription $subscription, int $amountKopecks): InitiatedBill
            {
                return new InitiatedBill('https://pay.test/bill-42', 42);
            }
        });

        $user = User::factory()->create();

        $outcome = $this->manager()->purchase($user, 1);

        $this->assertFalse($outcome->activated);
        $this->assertSame('https://pay.test/bill-42', $outcome->bill->payUrl);
        $this->assertSame(Subscription::STATUS_PENDING, $outcome->subscription->fresh()->status);
    }
}
