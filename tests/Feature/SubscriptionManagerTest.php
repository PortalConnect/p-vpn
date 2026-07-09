<?php

namespace Tests\Feature;

use App\Jobs\CreateVpnKeyJob;
use App\Jobs\RestoreVpnKeyJob;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VpnKey;
use App\Models\WalletTransaction;
use PortalConnect\Subscriptions\Pricing;
use PortalConnect\Subscriptions\SubscriptionManager;
use PortalConnect\Wallet\Exceptions\InsufficientFundsException;
use PortalConnect\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionManagerTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionManager $manager;
    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = $this->app->make(SubscriptionManager::class);
        $this->wallet = $this->app->make(WalletService::class);
        Queue::fake();
    }

    public function test_activate_debits_balance_and_marks_active_without_auto_creating_key(): void
    {
        $user = User::factory()->create();
        $this->wallet->credit($user->wallet, Pricing::priceFor(1), WalletTransaction::TYPE_TOPUP);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
        ]);

        $this->manager->activate($sub);

        $sub->refresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertNotNull($sub->starts_at);
        $this->assertNotNull($sub->ends_at);
        $this->assertNotNull($sub->paid_via_transaction_id);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
        // ключ НЕ создаётся автоматически — юзер сам выберет локацию через /keys
        Queue::assertNotPushed(CreateVpnKeyJob::class);
        Queue::assertNotPushed(RestoreVpnKeyJob::class);
    }

    public function test_activate_throws_when_balance_insufficient_and_does_not_change_subscription(): void
    {
        $user = User::factory()->create();
        $this->wallet->credit($user->wallet, Pricing::priceFor(1) - 1, WalletTransaction::TYPE_TOPUP);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
        ]);

        $this->expectException(InsufficientFundsException::class);

        try {
            $this->manager->activate($sub);
        } finally {
            $sub->refresh();
            $this->assertSame(Subscription::STATUS_PENDING, $sub->status);
            $this->assertNull($sub->starts_at);
            Queue::assertNotPushed(RestoreVpnKeyJob::class);
        }
    }

    public function test_activate_extends_ends_at_from_previous_when_renewing_early(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        $user = User::factory()->create();

        $previousEndsAt = Carbon::parse('2026-05-10 12:00:00');
        Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => Carbon::parse('2026-04-10 12:00:00'),
            'ends_at' => $previousEndsAt,
        ]);

        $this->wallet->credit($user->wallet, Pricing::priceFor(1), WalletTransaction::TYPE_TOPUP);

        $newSub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
        ]);

        $this->manager->activate($newSub);

        $newSub->refresh();
        $this->assertTrue($newSub->starts_at->equalTo($previousEndsAt));
        $this->assertTrue($newSub->ends_at->equalTo($previousEndsAt->copy()->addMonth()));
    }

    public function test_activate_starts_from_now_when_no_active_subscription(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        $user = User::factory()->create();
        $this->wallet->credit($user->wallet, Pricing::priceFor(3), WalletTransaction::TYPE_TOPUP);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => 3,
            'price_kopecks' => Pricing::priceFor(3),
        ]);

        $this->manager->activate($sub);

        $sub->refresh();
        $this->assertTrue($sub->starts_at->equalTo(Carbon::now()));
        $this->assertTrue($sub->ends_at->equalTo(Carbon::now()->addMonths(3)));
    }

    public function test_activate_restores_revoked_key_within_grace_instead_of_creating_new(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        $user = User::factory()->create();

        $oldSub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_EXPIRED,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => Carbon::parse('2026-03-25 12:00:00'),
            'ends_at' => Carbon::parse('2026-04-24 12:00:00'),
        ]);

        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $oldSub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 42,
            'name' => 'old',
            'status' => VpnKey::STATUS_REVOKED,
            'revoked_at' => Carbon::now()->subDays(2),
        ]);

        $this->wallet->credit($user->wallet, Pricing::priceFor(1), WalletTransaction::TYPE_TOPUP);
        $newSub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
        ]);

        $this->manager->activate($newSub);

        Queue::assertPushed(RestoreVpnKeyJob::class, function (RestoreVpnKeyJob $job) use ($key, $newSub) {
            return $job->key->is($key) && $job->subscription->is($newSub);
        });
        Queue::assertNotPushed(CreateVpnKeyJob::class);
    }

    public function test_activate_does_not_restore_when_revoked_key_outside_grace(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        $user = User::factory()->create();

        VpnKey::create([
            'user_id' => $user->id,
            'panel_server_id' => 1,
            'panel_client_id' => 42,
            'name' => 'old',
            'status' => VpnKey::STATUS_REVOKED,
            'revoked_at' => Carbon::now()->subDays(10),
        ]);

        $this->wallet->credit($user->wallet, Pricing::priceFor(1), WalletTransaction::TYPE_TOPUP);
        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
        ]);

        $this->manager->activate($sub);

        // юзер сам выберет локацию через /keys — ничего не пушим
        Queue::assertNotPushed(CreateVpnKeyJob::class);
        Queue::assertNotPushed(RestoreVpnKeyJob::class);
    }

    public function test_auto_renew_creates_new_subscription_and_charges_balance(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        $user = User::factory()->create();
        $this->wallet->credit($user->wallet, Pricing::priceFor(1), WalletTransaction::TYPE_TOPUP);

        $expiring = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => Carbon::parse('2026-03-25 12:00:00'),
            'ends_at' => Carbon::now(),
        ]);

        $renewed = $this->manager->autoRenewIfPossible($expiring);

        $this->assertNotNull($renewed);
        $this->assertSame(Subscription::STATUS_ACTIVE, $renewed->status);
        $this->assertSame($expiring->id, $renewed->auto_renewed_from_id);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
    }

    public function test_auto_renew_returns_null_when_auto_renew_is_off(): void
    {
        $user = User::factory()->create();
        $user->wallet->update(['auto_renew' => false]);
        $this->wallet->credit($user->wallet, Pricing::priceFor(1), WalletTransaction::TYPE_TOPUP);

        $expiring = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subMonth(),
            'ends_at' => now(),
        ]);

        $this->assertNull($this->manager->autoRenewIfPossible($expiring));
        $this->assertSame(Pricing::priceFor(1), $user->wallet->fresh()->balance_kopecks);
    }

    public function test_auto_renew_returns_null_when_balance_insufficient(): void
    {
        $user = User::factory()->create();
        $this->wallet->credit($user->wallet, Pricing::priceFor(1) - 1, WalletTransaction::TYPE_TOPUP);

        $expiring = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subMonth(),
            'ends_at' => now(),
        ]);

        $this->assertNull($this->manager->autoRenewIfPossible($expiring));
    }
}
