<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \PortalConnect\Subscriptions\Contracts\PaymentInitiator::class,
            \App\Services\Payments\PaymentInitiatorAdapter::class
        );
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        User::observe(UserObserver::class);
    }
}
