<?php

namespace PortalConnect\Subscriptions\Tests;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PortalConnect\Subscriptions\Models\Plan;
use PortalConnect\Subscriptions\Models\Subscription;
use PortalConnect\Subscriptions\Pricing;
use PortalConnect\Wallet\WalletService;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private function fundedUser(int $months = 1): User
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user->wallet, Pricing::priceFor($months), WalletTransaction::TYPE_TOPUP);
        return $user->fresh();
    }

    public function test_user_trait_full_cycle(): void
    {
        $user = $this->fundedUser();

        $this->assertFalse($user->hasActiveSubscription());

        $outcome = $user->newSubscription(1);

        $this->assertTrue($outcome->activated);
        $this->assertTrue($user->hasActiveSubscription());
        $this->assertTrue($user->subscribedFor(1));
        $this->assertFalse($user->subscribedFor(3));

        $sub = $user->activeSubscription();
        $this->assertTrue($sub->active());
        $this->assertFalse($sub->ended());
        $this->assertGreaterThan(25, $sub->daysLeft());
    }

    public function test_cancel_immediately_closes_access(): void
    {
        $user = $this->fundedUser();
        $user->newSubscription(1);
        $sub = $user->activeSubscription();

        $sub->cancel(immediately: true);

        $this->assertTrue($sub->fresh()->canceled());
        $this->assertFalse($user->fresh()->hasActiveSubscription());
    }

    public function test_renew_charges_wallet_and_extends(): void
    {
        $user = $this->fundedUser();
        $user->newSubscription(1);
        $first = $user->activeSubscription();

        // Пополняем на второй период и продлеваем
        app(WalletService::class)->credit($user->wallet->fresh(), Pricing::priceFor(1), WalletTransaction::TYPE_TOPUP);
        $outcome = $first->renew();

        $this->assertTrue($outcome->activated);
        // Продление состыковано к концу первого периода
        $this->assertTrue($outcome->subscription->fresh()->ends_at->gt($first->fresh()->ends_at));
    }

    public function test_plans_from_db_override_config_prices(): void
    {
        Plan::create(['slug' => 'm1', 'name' => '1 месяц', 'months' => 1, 'price_kopecks' => 33300, 'sort_order' => 1]);

        $this->assertSame(33300, Pricing::priceFor(1));
        $this->assertSame([1 => 33300], Pricing::all());

        $user = User::factory()->create();
        app(WalletService::class)->credit($user->wallet, 33300, WalletTransaction::TYPE_TOPUP);
        $outcome = $user->fresh()->newSubscription(1);

        $this->assertTrue($outcome->activated);
        $this->assertSame('m1', Subscription::find($outcome->subscription->id)->plan->slug);
    }
}
