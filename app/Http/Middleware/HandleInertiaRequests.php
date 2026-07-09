<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => $locale,
            'available_locales' => ['ru', 'en'],
            'translations' => trans('portal'),
            // Валюта следует за локалью — фронт форматирует суммы через useMoney().
            'currency' => app(\App\Services\Currency\CurrencyService::class)->forLocale($locale),
        ];
    }

    private function resolveLocale(Request $request): string
    {
        $supported = ['ru', 'en'];
        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        $candidate = $sessionLocale ?? config('app.locale', 'ru');

        return in_array($candidate, $supported, true) ? $candidate : 'ru';
    }
}
