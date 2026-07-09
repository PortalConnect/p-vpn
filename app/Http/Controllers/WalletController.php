<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $wallet = $user->wallet;

        $transactions = $wallet->transactions()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'type', 'amount_kopecks', 'balance_after_kopecks', 'description', 'created_at']);

        return Inertia::render('Wallet/Index', [
            'wallet' => [
                'balance_kopecks' => $wallet->balance_kopecks,
                'auto_renew' => $wallet->auto_renew,
                'currency' => $wallet->currency,
            ],
            'transactions' => $transactions,
            'min_topup_rubles' => (int) config('wallet.min_topup_rubles'),
        ]);
    }

    public function topup(Request $request): RedirectResponse
    {
        $min = (int) config('wallet.min_topup_rubles');
        $data = $request->validate([
            'amount_rubles' => "required|integer|min:{$min}|max:1000000",
        ]);

        $user = $request->user();
        $amountKopecks = (int) $data['amount_rubles'] * 100;

        $payment = $this->payments->walletTopup($user, $amountKopecks);

        return redirect()->away($payment->pay_url);
    }

    public function toggleAutoRenew(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'auto_renew' => 'required|boolean',
        ]);

        $request->user()->wallet->update(['auto_renew' => $data['auto_renew']]);

        return redirect('/wallet');
    }
}
