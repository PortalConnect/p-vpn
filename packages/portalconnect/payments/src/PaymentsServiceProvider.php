<?php

namespace PortalConnect\Payments;

use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payments.php', 'payments');
        $this->mergeConfigFrom(__DIR__ . '/../config/freekassa.php', 'freekassa');
        $this->mergeConfigFrom(__DIR__ . '/../config/cardlink.php', 'cardlink');

        $this->app->singleton(PaymentManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/payments.php' => config_path('payments.php'),
            __DIR__ . '/../config/freekassa.php' => config_path('freekassa.php'),
            __DIR__ . '/../config/cardlink.php' => config_path('cardlink.php'),
        ], 'pvpn-payments-config');
    }
}
