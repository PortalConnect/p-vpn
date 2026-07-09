<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PortalConnect\Subscriptions\Models\Subscription as BaseSubscription;

class Subscription extends BaseSubscription
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    public function paidViaTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'paid_via_transaction_id');
    }

    public function vpnKeys(): HasMany
    {
        return $this->hasMany(VpnKey::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }
}
