<?php

namespace PortalConnect\Subscriptions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use PortalConnect\Subscriptions\Models\Subscription;

/** Подписка оплачена и активирована — приложение решает, что делать дальше (например, восстановить VPN-ключ). */
class SubscriptionActivated
{
    use Dispatchable;

    public function __construct(public readonly Subscription $subscription)
    {
    }
}
