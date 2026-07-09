<?php

namespace PortalConnect\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsage extends Model
{
    protected $table = 'subscription_usage';

    protected $fillable = ['subscription_id', 'feature_slug', 'used'];

    protected $casts = ['used' => 'integer'];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.model', Subscription::class), 'subscription_id');
    }
}
