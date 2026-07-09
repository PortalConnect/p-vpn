<?php

namespace PortalConnect\Subscriptions\Tests;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PortalConnect\Subscriptions\Models\Plan;
use PortalConnect\Subscriptions\Pricing;
use PortalConnect\Wallet\WalletService;
use Tests\TestCase;

class PlanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(
            \PortalConnect\Subscriptions\Contracts\PaymentInitiator::class,
            fn () => new class implements \PortalConnect\Subscriptions\Contracts\PaymentInitiator {
                public function initiateSubscriptionPurchase(object $user, \PortalConnect\Subscriptions\Models\Subscription $subscription, int $amountKopecks): \PortalConnect\Subscriptions\DTO\InitiatedBill
                {
                    return new \PortalConnect\Subscriptions\DTO\InitiatedBill('https://pay.test/fake', null);
                }
            }
        );
    }

    private function planWithFeatures(): Plan
    {
        $plan = Plan::create(['slug' => 'pro', 'name' => 'Pro', 'months' => 1, 'price_kopecks' => 20000]);
        $plan->features()->createMany([
            ['slug' => 'devices', 'name' => 'Устройства', 'value' => 2],
            ['slug' => 'traffic', 'name' => 'Трафик', 'value' => null],
        ]);
        return $plan;
    }

    private function subscribe(User $user, Plan $plan)
    {
        app(WalletService::class)->credit($user->wallet, $plan->price_kopecks, WalletTransaction::TYPE_TOPUP);
        return $user->fresh()->newSubscription($plan)->subscription;
    }

    public function test_feature_usage_lifecycle(): void
    {
        $plan = $this->planWithFeatures();
        $sub = $this->subscribe(User::factory()->create(), $plan);

        $this->assertTrue($sub->canUseFeature('devices'));
        $this->assertSame(2, $sub->getFeatureRemainings('devices'));
        $this->assertNull($sub->getFeatureRemainings('traffic')); // безлимит

        $this->assertTrue($sub->recordFeatureUsage('devices'));
        $this->assertTrue($sub->recordFeatureUsage('devices'));
        $this->assertFalse($sub->recordFeatureUsage('devices')); // лимит 2
        $this->assertSame(0, $sub->getFeatureRemainings('devices'));
        $this->assertFalse($sub->canUseFeature('devices'));

        $sub->reduceFeatureUsage('devices');
        $this->assertTrue($sub->canUseFeature('devices'));

        $this->assertFalse($sub->canUseFeature('unknown'));
    }

    public function test_trial_activates_without_charge_only_once(): void
    {
        $plan = Plan::create(['slug' => 'trial', 'name' => 'Trial', 'months' => 1, 'price_kopecks' => 20000, 'trial_days' => 7]);
        $user = User::factory()->create(); // кошелёк пуст

        $outcome = $user->newSubscription($plan);

        $this->assertTrue($outcome->activated);
        $sub = $outcome->subscription->fresh();
        $this->assertTrue($sub->onTrial());
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
        $this->assertEqualsWithDelta(7, now()->diffInDays($sub->ends_at), 1);

        // Второй раз триала нет: без денег — счёт на оплату
        $second = $user->fresh()->newSubscription($plan);
        $this->assertFalse($second->activated);
    }

    public function test_change_plan_refunds_prorata_and_switches(): void
    {
        $m1 = Plan::create(['slug' => 'm1', 'name' => '1 мес', 'months' => 1, 'price_kopecks' => 20000]);
        $m3 = Plan::create(['slug' => 'm3', 'name' => '3 мес', 'months' => 3, 'price_kopecks' => 57000]);

        $user = User::factory()->create();
        app(WalletService::class)->credit($user->wallet, 20000 + 57000, WalletTransaction::TYPE_TOPUP);
        $user = $user->fresh();

        $sub = $user->newSubscription($m1)->subscription; // остался 57000

        $outcome = app(\PortalConnect\Subscriptions\SubscriptionManager::class)->changePlan($sub->fresh(), $m3);

        $this->assertTrue($outcome->activated);
        $this->assertTrue($sub->fresh()->canceled());
        $this->assertTrue($user->fresh()->subscribedTo('m3'));
        // Возврат за неиспользованный месяц (~20000) вернулся на баланс
        $this->assertGreaterThan(15000, $user->wallet->fresh()->balance_kopecks);
    }

    public function test_named_subscription_tags(): void
    {
        $plan = Plan::create(['slug' => 'addon', 'name' => 'Addon', 'months' => 1, 'price_kopecks' => 10000]);
        $user = User::factory()->create();
        app(WalletService::class)->credit($user->wallet, 30000, WalletTransaction::TYPE_TOPUP);
        $user = $user->fresh();

        $user->newSubscription(1);              // main (цена из конфига)
        $user->newSubscription($plan, 'addon'); // отдельный тег

        $this->assertNotNull($user->subscription('main'));
        $this->assertNotNull($user->subscription('addon'));
        $this->assertNull($user->subscription('other'));
        $this->assertTrue($user->subscribedTo('addon'));
    }
}
