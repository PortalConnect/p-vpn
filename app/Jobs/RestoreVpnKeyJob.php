<?php

namespace App\Jobs;

use PortalConnect\Subscriptions\Models\Subscription;
use App\Models\VpnKey;
use App\Services\Keys\KeyProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RestoreVpnKeyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public VpnKey $key, public Subscription $subscription)
    {
    }

    public function handle(KeyProvisioningService $service): void
    {
        $service->restore($this->key, $this->subscription);
    }
}
