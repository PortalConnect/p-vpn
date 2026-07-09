<?php

namespace PortalConnect\Wallet;

use Illuminate\Support\ServiceProvider;
use PortalConnect\Wallet\Console\TopupCommand;

class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/wallet.php', 'wallet');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/wallet.php' => config_path('wallet.php'),
        ], 'portalconnect-wallet-config');

        if ($this->app->runningInConsole()) {
            $this->commands([TopupCommand::class]);
        }
    }
}
