<?php

use App\Http\Controllers\KeyController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WalletController;
use App\Models\Subscription;
use PortalConnect\Subscriptions\Pricing;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Публичные юридические страницы: /about, /about/privacy|terms|contacts
Route::get('/about/{section?}', function (?string $section = 'privacy') {
    abort_unless(in_array($section, ['privacy', 'terms', 'contacts'], true), 404);

    return Inertia::render('Legal', [
        'section' => $section,
        'support' => [
            'email' => config('services.support.email'),
            'telegram' => config('services.support.telegram'),
            'operator' => config('services.support.operator'),
            'operator_details' => config('services.support.operator_details'),
        ],
    ]);
})->name('about');

Route::get('/pricing', function () {
    return Inertia::render('Pricing', [
        'prices' => Pricing::all(),
    ]);
})->name('pricing');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        $user = auth()->user();
        return Inertia::render('Dashboard', [
            'wallet' => [
                'balance_kopecks' => $user->wallet->balance_kopecks,
                'auto_renew' => $user->wallet->auto_renew,
            ],
            'active_subscription' => $user->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->orderByDesc('ends_at')
                ->first(),
            'vpn_key' => $user->vpnKeys()->where('status', 'active')->first(),
            'prices' => Pricing::all(),
        ]);
    })->name('dashboard');

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/topup', [WalletController::class, 'topup'])->name('wallet.topup');
    Route::post('/wallet/auto-renew', [WalletController::class, 'toggleAutoRenew'])->name('wallet.auto-renew');

    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');

    Route::get('/keys', [KeyController::class, 'index'])->name('keys.index');
    Route::post('/keys', [KeyController::class, 'store'])->name('keys.store');
    Route::post('/keys/{key}/change-location', [KeyController::class, 'changeLocation'])->name('keys.change-location');
    Route::get('/keys/{key}/download', [KeyController::class, 'download'])->name('keys.download');
    Route::get('/keys/{key}/qr', [KeyController::class, 'qr'])->name('keys.qr');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

require __DIR__.'/auth.php';
