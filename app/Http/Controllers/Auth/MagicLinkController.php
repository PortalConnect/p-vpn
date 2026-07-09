<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginLink;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MagicLinkController extends Controller
{
    /**
     * Шаг 1: пользователь вводит email — создаём (или находим) аккаунт
     * и отправляем одноразовую ссылку для входа.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($request->input('email')));

        $throttleKey = 'magic-link:' . $email . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => __('portal.auth.throttled', ['seconds' => $seconds]),
            ]);
        }
        RateLimiter::hit($throttleKey, 300);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::before($email, '@'),
                // Парольный вход не используется — ставим случайный.
                'password' => bcrypt(Str::random(40)),
            ]
        );

        [, $plain] = LoginToken::issue($user, $request->ip());

        Mail::to($user)->queue(new LoginLink(
            route('login.magic.verify', ['token' => $plain])
        ));

        return back()->with('status', 'magic-link-sent');
    }

    /**
     * Шаг 2: переход по ссылке из письма — логиним и подтверждаем email
     * (клик по ссылке доказывает владение ящиком).
     */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $loginToken = LoginToken::findUsable($token);

        if ($loginToken === null) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => __('portal.auth.link_invalid')]);
        }

        $loginToken->markUsed();

        $user = $loginToken->user;
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
