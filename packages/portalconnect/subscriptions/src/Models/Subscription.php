<?php

namespace PortalConnect\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'status',
        'months',
        'price_kopecks',
        'starts_at',
        'ends_at',
        'paid_via_transaction_id',
        'auto_renewed_from_id',
    ];

    protected $casts = [
        'months' => 'integer',
        'price_kopecks' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.user_model', \App\Models\User::class));
    }
}
