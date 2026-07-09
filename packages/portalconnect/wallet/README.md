# portalconnect/wallet

Внутренний кошелёк-леджер: `Wallet`/`WalletTransaction` (баланс в копейках,
история с balance_after), `WalletService::credit/debit/refund` — каждая
операция под `SELECT ... FOR UPDATE` в одной БД-транзакции, типы операций
валидируются.

Команда: `php artisan wallet:topup {user_id} {amount_rubles} {--note=}` —
ручное начисление (тип manual_credit).

Конфиг `config/wallet.php`: `model`, `transaction_model`, `user_model`,
`min_topup_rubles`, `grace_days`.
