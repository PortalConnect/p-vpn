<?php

namespace App\Console\Commands;

use App\Jobs\RevokeVpnKeyJob;
use App\Mail\SubscriptionAutoRenewed;
use App\Mail\SubscriptionExpired;
use App\Mail\TopupRequired;
use App\Models\ReminderLog;
use App\Models\Subscription;
use PortalConnect\Subscriptions\Models\Subscription as BaseSubscription;
use App\Models\VpnKey;
use App\Services\Billing\TopupBillFactory;
use PortalConnect\Subscriptions\SubscriptionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Истечение подписок: автосписание, срочные уведомления, revoke ключей после grace';

    public function handle(
        SubscriptionManager $subscriptions,
        TopupBillFactory $billFactory,
    ): int {
        $this->processExpiringActive($subscriptions, $billFactory);
        $this->processGraceEnded();

        return self::SUCCESS;
    }

    private function processExpiringActive(
        SubscriptionManager $subscriptions,
        TopupBillFactory $billFactory,
    ): void {
        Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '<', now())
            ->orderBy('ends_at')
            ->chunkById(50, function ($expiring) use ($subscriptions, $billFactory) {
                foreach ($expiring as $sub) {
                    $renewed = $subscriptions->autoRenewIfPossible($sub);

                    if ($renewed) {
                        $sub->update(['status' => Subscription::STATUS_EXPIRED]);
                        $this->reattachKeyToNewSubscription($sub, $renewed);

                        Mail::to($sub->user)->queue(new SubscriptionAutoRenewed(
                            $renewed,
                            $sub->user->wallet->fresh()->balance_kopecks,
                        ));

                        ReminderLog::firstOrCreate(
                            ['subscription_id' => $sub->id, 'kind' => ReminderLog::KIND_AUTO_RENEWED],
                            ['sent_at' => now()]
                        );

                        continue;
                    }

                    $this->markExpiredAndNotifyShortfall($sub, $subscriptions, $billFactory);
                }
            });
    }

    private function markExpiredAndNotifyShortfall(
        Subscription $sub,
        SubscriptionManager $subscriptions,
        TopupBillFactory $billFactory,
    ): void {
        $sub->update(['status' => Subscription::STATUS_EXPIRED]);

        $alreadyNotified = ReminderLog::where('subscription_id', $sub->id)
            ->where('kind', ReminderLog::KIND_EXPIRED)
            ->exists();
        if ($alreadyNotified) {
            return;
        }

        $shortfall = $subscriptions->shortfall($sub->user, $sub->months);
        $minTopup = (int) config('wallet.min_topup_rubles') * 100;
        $amountToBill = max($shortfall, $minTopup);

        $newSub = Subscription::create([
            'user_id' => $sub->user_id,
            'status' => Subscription::STATUS_PENDING,
            'months' => $sub->months,
            'price_kopecks' => $sub->price_kopecks,
        ]);

        try {
            $payment = $billFactory->forSubscriptionPurchase($sub->user, $newSub, $amountToBill);
            Mail::to($sub->user)->queue(new TopupRequired($sub, $shortfall, $payment->pay_url));
        } catch (\Throwable $e) {
            Log::error('expire: cardlink bill creation failed', ['subscription_id' => $sub->id, 'error' => $e->getMessage()]);
        }

        ReminderLog::firstOrCreate(
            ['subscription_id' => $sub->id, 'kind' => ReminderLog::KIND_EXPIRED],
            ['sent_at' => now()]
        );
    }

    private function processGraceEnded(): void
    {
        $graceDays = (int) config('wallet.grace_days', 3);

        Subscription::query()
            ->where('status', Subscription::STATUS_EXPIRED)
            ->where('ends_at', '<', now()->subDays($graceDays))
            ->whereDoesntHave('reminderLogs', function ($q) {
                $q->where('kind', ReminderLog::KIND_GRACE_END);
            })
            ->chunkById(50, function ($subs) {
                foreach ($subs as $sub) {
                    $key = VpnKey::where('subscription_id', $sub->id)
                        ->where('status', VpnKey::STATUS_ACTIVE)
                        ->first();

                    if ($key) {
                        RevokeVpnKeyJob::dispatch($key);
                    }

                    Mail::to($sub->user)->queue(new SubscriptionExpired($sub));
                    ReminderLog::firstOrCreate(
                        ['subscription_id' => $sub->id, 'kind' => ReminderLog::KIND_GRACE_END],
                        ['sent_at' => now()]
                    );
                }
            });
    }

    private function reattachKeyToNewSubscription(Subscription $expiring, BaseSubscription $renewed): void
    {
        VpnKey::where('subscription_id', $expiring->id)
            ->where('status', VpnKey::STATUS_ACTIVE)
            ->update(['subscription_id' => $renewed->id]);
    }
}
