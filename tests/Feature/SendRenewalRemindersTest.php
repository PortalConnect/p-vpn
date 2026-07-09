<?php

namespace Tests\Feature;

use App\Mail\TopupNeeded;
use App\Models\ReminderLog;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Pricing;
use PortalConnect\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendRenewalRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cardlink.api_url' => 'https://cardlink.test/api/v1',
            'cardlink.secret_key' => 'sk',
            'cardlink.shop_id' => 'shop',
            'wallet.min_topup_kopecks' => 10000,
        ]);
        Mail::fake();
        Http::fake([
            'cardlink.test/*' => Http::response([
                'bill_id' => 'bill_renewal',
                'link' => 'https://cardlink.test/pay/renewal',
            ], 200),
        ]);
    }

    public function test_sends_d7_email_when_balance_insufficient(): void
    {
        Carbon::setTestNow('2026-04-25 10:00:00');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subDays(23),
            'ends_at' => now()->addDays(7),
        ]);

        $this->artisan('subscriptions:remind')->assertOk();

        Mail::assertQueued(TopupNeeded::class, function (TopupNeeded $m) use ($sub) {
            return $m->subscription->is($sub) && $m->daysLeft === 7;
        });
        $this->assertDatabaseHas('reminder_logs', [
            'subscription_id' => $sub->id,
            'kind' => ReminderLog::KIND_TOPUP_NEEDED_D7,
        ]);
    }

    public function test_does_not_send_when_balance_is_sufficient(): void
    {
        Carbon::setTestNow('2026-04-25 10:00:00');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->app->make(WalletService::class)->credit(
            $user->wallet,
            Pricing::priceFor(1),
            WalletTransaction::TYPE_TOPUP
        );

        Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subDays(23),
            'ends_at' => now()->addDays(7),
        ]);

        $this->artisan('subscriptions:remind')->assertOk();

        Mail::assertNothingQueued();
        $this->assertSame(0, ReminderLog::count());
    }

    public function test_does_not_send_d7_twice(): void
    {
        Carbon::setTestNow('2026-04-25 10:00:00');
        $user = User::factory()->create(['email_verified_at' => now()]);

        Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subDays(23),
            'ends_at' => now()->addDays(7),
        ]);

        $this->artisan('subscriptions:remind')->assertOk();
        $this->artisan('subscriptions:remind')->assertOk();

        Mail::assertQueued(TopupNeeded::class, 1);
    }

    public function test_sends_d3_window(): void
    {
        Carbon::setTestNow('2026-04-25 10:00:00');
        $user = User::factory()->create(['email_verified_at' => now()]);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => Pricing::priceFor(1),
            'starts_at' => now()->subDays(27),
            'ends_at' => now()->addDays(3),
        ]);

        $this->artisan('subscriptions:remind')->assertOk();

        Mail::assertQueued(TopupNeeded::class, function (TopupNeeded $m) {
            return $m->daysLeft === 3;
        });
        $this->assertDatabaseHas('reminder_logs', [
            'subscription_id' => $sub->id,
            'kind' => ReminderLog::KIND_TOPUP_NEEDED_D3,
        ]);
    }
}
