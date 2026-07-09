<?php

namespace PortalConnect\Wallet\Console;

use Illuminate\Console\Command;
use PortalConnect\Wallet\Models\Wallet;
use PortalConnect\Wallet\Models\WalletTransaction;
use PortalConnect\Wallet\WalletService;

/**
 * Ручное пополнение кошелька: php artisan wallet:topup {user_id} {rubles}
 */
class TopupCommand extends Command
{
    protected $signature = 'wallet:topup
        {user_id : ID пользователя}
        {amount_rubles : Сумма пополнения в рублях}
        {--note= : Комментарий к операции}';

    protected $description = 'Пополнить кошелёк пользователя (ручное начисление, тип manual_credit)';

    public function handle(WalletService $wallet): int
    {
        $userId = (int) $this->argument('user_id');
        $rubles = (float) $this->argument('amount_rubles');

        if ($rubles <= 0) {
            $this->error('Сумма должна быть больше нуля.');
            return self::FAILURE;
        }

        $userModel = config('wallet.user_model', \App\Models\User::class);
        $user = $userModel::find($userId);
        if (!$user) {
            $this->error("Пользователь #{$userId} не найден.");
            return self::FAILURE;
        }

        $walletModel = config('wallet.model', Wallet::class);
        $walletRow = $walletModel::firstOrCreate(
            ['user_id' => $user->id],
            ['balance_kopecks' => 0, 'currency' => 'RUB']
        );

        $tx = $wallet->credit(
            $walletRow,
            (int) round($rubles * 100),
            WalletTransaction::TYPE_MANUAL_CREDIT,
            ['description' => $this->option('note') ?? 'Пополнение через wallet:topup']
        );

        $this->info(sprintf(
            'Кошелёк #%d (%s) пополнен на %.2f ₽. Баланс: %.2f ₽ (транзакция #%d)',
            $walletRow->id,
            $user->email,
            $rubles,
            $tx->balance_after_kopecks / 100,
            $tx->id
        ));

        return self::SUCCESS;
    }
}
