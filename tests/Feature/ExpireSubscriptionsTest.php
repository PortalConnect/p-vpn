<?php

namespace Tests\Feature;

use App\Jobs\RevokeVpnKeyJob;
use App\Mail\SubscriptionAutoRenewed;
use App\Mail\SubscriptionExpired;
use App\Mail\TopupRequired;
use App\Models\ReminderLog;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VpnKey;
use App\Models\WalletTransaction;
use App\Services\Pricing;
use PortalConnect\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpireSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cardlink.api_url' => 'https://cardlink.test/api/v1',
            'cardlink.secret_key' => 'sk_cron',
            'cardlink.shop_id' => 'shop_cron',
            'wallet.min_topup_kopecks' => 10000,
            'wallet.grace_days' => 3,
        ]);
        Mail::fake();
        Queue::fake();
    }

    public function test_expired_with_balance_and_auto_renew_creates_new_active_subscription(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->app->make(WalletService::class)->credit(
            $user->wallet,
            Pricing::priceFor(1),
            WalletTransaction::TYPE_TOPUP
        );

        $expiring = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subSecond(),
        ]);

        $this->artisan('subscriptions:expire')->assertOk();

        $expiring->refresh();
        $this->assertSame(Subscription::STATUS_EXPIRED, $expiring->status);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'auto_renewed_from_id' => $expiring->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertSame(0, $user->wallet->fresh()->balance_kopecks);
        Mail::assertQueued(SubscriptionAutoRenewed::class);
        Mail::assertNotQueued(TopupRequired::class);
        $this->assertDatabaseHas('reminder_logs', [
            'subscription_id' => $expiring->id,
            'kind' => ReminderLog::KIND_AUTO_RENEWED,
        ]);
    }

    public function test_expired_without_balance_marks_expired_and_sends_topup_required_email_with_pay_url(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        Http::fake([
            'cardlink.test/*' => Http::response([
                'bill_id' => 'bill_emergency',
                'link' => 'https://cardlink.test/pay/emergency',
            ], 200),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $expiring = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subSecond(),
        ]);

        $this->artisan('subscriptions:expire')->assertOk();

        $expiring->refresh();
        $this->assertSame(Subscription::STATUS_EXPIRED, $expiring->status);
        Mail::assertQueued(TopupRequired::class, function (TopupRequired $mail) use ($expiring) {
            return $mail->expiredSubscription->is($expiring)
                && $mail->shortfallKopecks === Pricing::priceFor(1)
                && str_contains($mail->payUrl, 'cardlink.test');
        });
        Mail::assertNotQueued(SubscriptionAutoRenewed::class);
        $this->assertDatabaseHas('reminder_logs', [
            'subscription_id' => $expiring->id,
            'kind' => ReminderLog::KIND_EXPIRED,
        ]);
    }

    public function test_expired_with_auto_renew_off_does_not_charge_balance_but_sends_topup_required(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        Http::fake([
            'cardlink.test/*' => Http::response([
                'bill_id' => 'bill_a',
                'link' => 'https://cardlink.test/pay/a',
            ], 200),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->wallet->update(['auto_renew' => false]);
        $this->app->make(WalletService::class)->credit(
            $user->wallet,
            Pricing::priceFor(1),
            WalletTransaction::TYPE_TOPUP
        );

        $expiring = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subSecond(),
        ]);

        $this->artisan('subscriptions:expire')->assertOk();

        $this->assertSame(Pricing::priceFor(1), $user->wallet->fresh()->balance_kopecks);
        Mail::assertQueued(TopupRequired::class);
    }

    public function test_grace_period_expired_revokes_key_and_sends_farewell(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_EXPIRED,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subDays(34),
            'ends_at' => now()->subDays(4),
        ]);
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 9,
            'name' => 'k',
            'status' => VpnKey::STATUS_ACTIVE,
        ]);

        $this->artisan('subscriptions:expire')->assertOk();

        Queue::assertPushed(RevokeVpnKeyJob::class, function (RevokeVpnKeyJob $job) use ($key) {
            return $job->key->is($key);
        });
        Mail::assertQueued(SubscriptionExpired::class);
        $this->assertDatabaseHas('reminder_logs', [
            'subscription_id' => $sub->id,
            'kind' => ReminderLog::KIND_GRACE_END,
        ]);
    }

    public function test_command_is_idempotent_within_grace(): void
    {
        Carbon::setTestNow('2026-04-25 12:00:00');
        Http::fake([
            'cardlink.test/*' => Http::response([
                'bill_id' => 'bill_x',
                'link' => 'https://cardlink.test/pay/x',
            ], 200),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subSecond(),
        ]);

        $this->artisan('subscriptions:expire')->assertOk();
        $this->artisan('subscriptions:expire')->assertOk();

        // только одно письмо TopupRequired (повторно не шлём — есть запись в reminder_logs)
        Mail::assertQueued(TopupRequired::class, 1);
    }
}
