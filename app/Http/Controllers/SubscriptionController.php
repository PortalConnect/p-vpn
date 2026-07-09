<?php

namespace App\Http\Controllers;

use PortalConnect\Subscriptions\Pricing;
use PortalConnect\Subscriptions\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionManager $manager)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'months' => ['required', 'integer', Rule::in(array_keys(Pricing::all()))],
        ]);

        $outcome = $this->manager->purchase($request->user(), (int) $data['months']);

        if ($outcome->activated) {
            return redirect('/dashboard')->with('success', 'Подписка активирована.');
        }

        return redirect()->away($outcome->bill->payUrl);
    }
}
