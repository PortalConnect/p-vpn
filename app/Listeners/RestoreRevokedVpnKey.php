<?php

namespace App\Listeners;

use App\Jobs\RestoreVpnKeyJob;
use App\Models\VpnKey;
use PortalConnect\Subscriptions\Events\SubscriptionActivated;

/**
 * Если у пользователя есть revoked-ключ в grace-периоде — восстанавливаем
 * (та же локация, без участия юзера). Нового ключа не создаём: локацию
 * пользователь выбирает сам через /keys.
 */
class RestoreRevokedVpnKey
{
    public function handle(SubscriptionActivated $event): void
    {
        $subscription = $event->subscription;

        $key = $subscription->user->vpnKeys()
            ->where('status', VpnKey::STATUS_REVOKED)
            ->where('revoked_at', '>', now()->subDays(3))
            ->orderByDesc('revoked_at')
            ->first();

        if ($key) {
            RestoreVpnKeyJob::dispatch($key, $subscription);
        }
    }
}
