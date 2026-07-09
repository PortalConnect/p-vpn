<?php

namespace App\Console\Commands;

use App\Mail\TopupNeeded;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\Subscription;
use App\Services\Billing\TopupBillFactory;
use PortalConnect\Subscriptions\SubscriptionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRenewalReminders extends Command
{
    protected $signature = 'subscriptions:remind';
    protected $description = 'Превентивные письма-напоминания о пополнении кошелька (d7/d3/d1)';

    /** @var array<int,string> daysLeft => reminder kind */
    private const WINDOWS = [
        7 => ReminderLog::KIND_TOPUP_NEEDED_D7,
        3 => ReminderLog::KIND_TOPUP_NEEDED_D3,
        1 => ReminderLog::KIND_TOPUP_NEEDED_D1,
    ];

    public function handle(SubscriptionManager $subscriptions, TopupBillFactory $billFactory): int
    {
        foreach (self::WINDOWS as $daysLeft => $kind) {
            $this->processWindow($daysLeft, $kind, $subscriptions, $billFactory);
        }

        return self::SUCCESS;
    }

    private function processWindow(int $daysLeft, string $kind, SubscriptionManager $subscriptions, TopupBillFactory $billFactory): void
    {
        $start = now()->addDays($daysLeft)->startOfDay();
        $end = now()->addDays($daysLeft)->endOfDay();

        Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereBetween('ends_at', [$start, $end])
            ->whereDoesntHave('reminderLogs', fn ($q) => $q->where('kind', $kind))
            ->chunkById(50, function ($subs) use ($daysLeft, $kind, $subscriptions, $billFactory) {
                foreach ($subs as $sub) {
                    if ($subscriptions->sufficientFor($sub->user, $sub->months)) {
                        continue;
                    }

                    $shortfall = $subscriptions->shortfall($sub->user, $sub->months);
                    $minTopup = (int) config('wallet.min_topup_rubles') * 100;
                    $amountToBill = max($shortfall, $minTopup);

                    $payment = $this->upsertPendingPaymentForSubscription($sub, $amountToBill, $billFactory);
                    if (!$payment) {
                        continue;
                    }

                    Mail::to($sub->user)->queue(new TopupNeeded($sub, $daysLeft, $shortfall, $payment->pay_url));
                    ReminderLog::firstOrCreate(
                        ['subscription_id' => $sub->id, 'kind' => $kind],
                        ['sent_at' => now()]
                    );
                }
            });
    }

    private function upsertPendingPaymentForSubscription(
        Subscription $sub,
        int $amountKopecks,
        TopupBillFactory $billFactory,
    ): ?Payment {
        $existing = Payment::query()
            ->where('intent', Payment::INTENT_SUBSCRIPTION_PURCHASE)
            ->whereHas('intentSubscription', fn ($q) => $q->where('user_id', $sub->user_id)->where('months', $sub->months))
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('pay_url')
            ->latest('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        try {
            $renewalSub = Subscription::where('user_id', $sub->user_id)
                ->where('status', Subscription::STATUS_PENDING)
                ->where('months', $sub->months)
                ->latest('id')
                ->first()
                ?? Subscription::create([
                    'user_id' => $sub->user_id,
                    'status' => Subscription::STATUS_PENDING,
                    'months' => $sub->months,
                    'price_kopecks' => $sub->price_kopecks,
                ]);

            return $billFactory->forSubscriptionPurchase($sub->user, $renewalSub, $amountKopecks);
        } catch (\Throwable $e) {
            Log::error('remind: cardlink bill creation failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
