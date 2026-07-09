<?php

namespace PortalConnect\Wallet;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PortalConnect\Wallet\Exceptions\InsufficientFundsException;
use PortalConnect\Wallet\Models\Wallet;
use PortalConnect\Wallet\Models\WalletTransaction;

/**
 * Ledger-операции кошелька. Гарантии:
 *  - каждая операция лочит строку кошелька (SELECT ... FOR UPDATE);
 *  - баланс и запись транзакции меняются в одной БД-транзакции;
 *  - тип операции валидируется (credit-типы нельзя списывать и наоборот).
 */
class WalletService
{
    public function credit(Wallet $wallet, int $amountKopecks, string $type, array $context = []): WalletTransaction
    {
        $this->assertPositive($amountKopecks);
        $this->assertCreditType($type);

        return DB::transaction(function () use ($wallet, $amountKopecks, $type, $context) {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
            $newBalance = $locked->balance_kopecks + $amountKopecks;

            $locked->balance_kopecks = $newBalance;
            $locked->save();

            $tx = WalletTransaction::create([
                'wallet_id' => $locked->id,
                'user_id' => $locked->user_id,
                'type' => $type,
                'amount_kopecks' => $amountKopecks,
                'balance_after_kopecks' => $newBalance,
                'related_payment_id' => $context['related_payment_id'] ?? null,
                'related_subscription_id' => $context['related_subscription_id'] ?? null,
                'description' => $context['description'] ?? null,
                'created_by_admin_id' => $context['created_by_admin_id'] ?? null,
            ]);

            $wallet->setRawAttributes($locked->getAttributes(), true);

            return $tx;
        });
    }

    public function debit(Wallet $wallet, int $amountKopecks, string $type, array $context = []): WalletTransaction
    {
        $this->assertPositive($amountKopecks);
        $this->assertDebitType($type);

        return DB::transaction(function () use ($wallet, $amountKopecks, $type, $context) {
            $locked = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            if ($locked->balance_kopecks < $amountKopecks) {
                throw new InsufficientFundsException(
                    "Wallet {$locked->id}: balance {$locked->balance_kopecks} < requested {$amountKopecks}"
                );
            }

            $newBalance = $locked->balance_kopecks - $amountKopecks;
            $locked->balance_kopecks = $newBalance;
            $locked->save();

            $tx = WalletTransaction::create([
                'wallet_id' => $locked->id,
                'user_id' => $locked->user_id,
                'type' => $type,
                'amount_kopecks' => -$amountKopecks,
                'balance_after_kopecks' => $newBalance,
                'related_payment_id' => $context['related_payment_id'] ?? null,
                'related_subscription_id' => $context['related_subscription_id'] ?? null,
                'description' => $context['description'] ?? null,
                'created_by_admin_id' => $context['created_by_admin_id'] ?? null,
            ]);

            $wallet->setRawAttributes($locked->getAttributes(), true);

            return $tx;
        });
    }

    /** Возврат средств (кредит с типом refund); контекст — related_subscription_id и т.п. */
    public function refund(Wallet $wallet, int $amountKopecks, array $context = []): WalletTransaction
    {
        return $this->credit($wallet, $amountKopecks, WalletTransaction::TYPE_REFUND, $context);
    }

    private function assertPositive(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Wallet operation amount must be > 0, got {$amount}");
        }
    }

    private function assertCreditType(string $type): void
    {
        $allowed = [
            WalletTransaction::TYPE_TOPUP,
            WalletTransaction::TYPE_REFUND,
            WalletTransaction::TYPE_BONUS,
            WalletTransaction::TYPE_MANUAL_CREDIT,
        ];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException("Type {$type} is not a credit type");
        }
    }

    private function assertDebitType(string $type): void
    {
        $allowed = [
            WalletTransaction::TYPE_SUBSCRIPTION_DEBIT,
            WalletTransaction::TYPE_MANUAL_DEBIT,
        ];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException("Type {$type} is not a debit type");
        }
    }
}
