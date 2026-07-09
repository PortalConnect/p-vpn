<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Pricing;
use App\Services\Wallet\Exceptions\InsufficientFundsException;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(WalletService::class);
    }

    public function test_credit_increases_balance_and_writes_transaction(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet;

        $tx = $this->service->credit($wallet, 5000, WalletTransaction::TYPE_TOPUP);

        $this->assertSame(5000, $wallet->fresh()->balance_kopecks);
        $this->assertSame(5000, $tx->balance_after_kopecks);
        $this->assertSame(WalletTransaction::TYPE_TOPUP, $tx->type);
        $this->assertSame(5000, $tx->amount_kopecks);
        $this->assertSame($wallet->id, $tx->wallet_id);
        $this->assertSame($user->id, $tx->user_id);
    }

    public function test_credit_accumulates_balance_across_multiple_calls(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet;

        $this->service->credit($wallet, 3000, WalletTransaction::TYPE_TOPUP);
        $this->service->credit($wallet, 7000, WalletTransaction::TYPE_TOPUP);

        $this->assertSame(10000, $wallet->fresh()->balance_kopecks);
    }

    public function test_credit_rejects_non_positive_amount(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet;

        $this->expectException(\InvalidArgumentException::class);
        $this->service->credit($wallet, 0, WalletTransaction::TYPE_TOPUP);
    }

    public function test_debit_decreases_balance_and_writes_transaction(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet;
        $this->service->credit($wallet, 20000, WalletTransaction::TYPE_TOPUP);

        $tx = $this->service->debit($wallet, 5000, WalletTransaction::TYPE_SUBSCRIPTION_DEBIT);

        $this->assertSame(15000, $wallet->fresh()->balance_kopecks);
        $this->assertSame(-5000, $tx->amount_kopecks);
        $this->assertSame(15000, $tx->balance_after_kopecks);
        $this->assertSame(WalletTransaction::TYPE_SUBSCRIPTION_DEBIT, $tx->type);
    }

    public function test_debit_throws_when_insufficient_funds(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet;
        $this->service->credit($wallet, 1000, WalletTransaction::TYPE_TOPUP);

        $this->expectException(InsufficientFundsException::class);

        try {
            $this->service->debit($wallet, 5000, WalletTransaction::TYPE_SUBSCRIPTION_DEBIT);
        } finally {
            $this->assertSame(1000, $wallet->fresh()->balance_kopecks, 'balance must be unchanged');
            $this->assertSame(1, WalletTransaction::count(), 'no debit tx written');
        }
    }

    public function test_debit_rejects_non_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->debit($user->wallet, 0, WalletTransaction::TYPE_SUBSCRIPTION_DEBIT);
    }

    public function test_refund_credits_balance_with_refund_type(): void
    {
        $user = User::factory()->create();
        $wallet = $user->wallet;
        $this->service->credit($wallet, 20000, WalletTransaction::TYPE_TOPUP);
        $this->service->debit($wallet, 20000, WalletTransaction::TYPE_SUBSCRIPTION_DEBIT);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => 20000,
        ]);

        $tx = $this->service->refund($wallet, 20000, $sub);

        $this->assertSame(20000, $wallet->fresh()->balance_kopecks);
        $this->assertSame(WalletTransaction::TYPE_REFUND, $tx->type);
        $this->assertSame(20000, $tx->amount_kopecks);
        $this->assertSame($sub->id, $tx->related_subscription_id);
    }

    public function test_sufficient_for_renewal_compares_balance_to_pricing(): void
    {
        $user = User::factory()->create();
        $price1m = Pricing::priceFor(1); // 20000

        $this->assertFalse($this->service->sufficientForRenewal($user, 1));

        $this->service->credit($user->wallet, $price1m - 1, WalletTransaction::TYPE_TOPUP);
        $user->refresh();
        $this->assertFalse($this->service->sufficientForRenewal($user, 1));

        $this->service->credit($user->wallet, 1, WalletTransaction::TYPE_TOPUP);
        $user->refresh();
        $this->assertTrue($this->service->sufficientForRenewal($user, 1));
    }

    public function test_shortfall_returns_missing_amount_or_zero(): void
    {
        $user = User::factory()->create();
        $price1m = Pricing::priceFor(1);

        $this->assertSame($price1m, $this->service->shortfall($user, 1));

        $this->service->credit($user->wallet, $price1m - 5000, WalletTransaction::TYPE_TOPUP);
        $user->refresh();
        $this->assertSame(5000, $this->service->shortfall($user, 1));

        $this->service->credit($user->wallet, 5000, WalletTransaction::TYPE_TOPUP);
        $user->refresh();
        $this->assertSame(0, $this->service->shortfall($user, 1));

        $this->service->credit($user->wallet, 100, WalletTransaction::TYPE_TOPUP);
        $user->refresh();
        $this->assertSame(0, $this->service->shortfall($user, 1));
    }

    public function test_credit_stores_context_fields(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $tx = $this->service->credit(
            $user->wallet,
            10000,
            WalletTransaction::TYPE_MANUAL_CREDIT,
            [
                'description' => 'компенсация за инцидент',
                'created_by_admin_id' => $admin->id,
            ]
        );

        $this->assertSame('компенсация за инцидент', $tx->description);
        $this->assertSame($admin->id, $tx->created_by_admin_id);
    }
}
