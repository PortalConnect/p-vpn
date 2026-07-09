<?php

namespace PortalConnect\Wallet\Tests;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_topup_command_credits_wallet(): void
    {
        $user = User::factory()->create();

        $this->artisan('wallet:topup', [
            'user_id' => $user->id,
            'amount_rubles' => 250,
            '--note' => 'тестовое начисление',
        ])->assertSuccessful();

        $this->assertSame(25000, $user->wallet->fresh()->balance_kopecks);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_MANUAL_CREDIT,
            'amount_kopecks' => 25000,
            'description' => 'тестовое начисление',
        ]);
    }

    public function test_topup_command_creates_wallet_if_missing(): void
    {
        $user = User::factory()->create();
        $user->wallet()->delete();

        $this->artisan('wallet:topup', [
            'user_id' => $user->id,
            'amount_rubles' => 100,
        ])->assertSuccessful();

        $this->assertSame(10000, $user->wallet()->first()->balance_kopecks);
    }

    public function test_topup_command_rejects_unknown_user_and_bad_amount(): void
    {
        $this->artisan('wallet:topup', ['user_id' => 999999, 'amount_rubles' => 100])
            ->assertFailed();

        $user = User::factory()->create();
        $this->artisan('wallet:topup', ['user_id' => $user->id, 'amount_rubles' => 0])
            ->assertFailed();
    }
}
