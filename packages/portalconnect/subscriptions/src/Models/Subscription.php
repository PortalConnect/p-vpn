<?php

namespace PortalConnect\Subscriptions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PortalConnect\Subscriptions\SubscriptionManager;

class Subscription extends Model
{
    protected $table = 'subscriptions';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'plan_id',
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // ------------------------------------------------------------------
    // Fluent-статусы (в духе laravelcm/laravel-subscriptions)
    // ------------------------------------------------------------------

    public function active(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function ended(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->ends_at !== null && $this->ends_at->isPast());
    }

    public function canceled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function pending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function daysLeft(): int
    {
        if (!$this->active()) {
            return 0;
        }

        return (int) now()->diffInDays($this->ends_at, false);
    }

    // ------------------------------------------------------------------
    // Управление
    // ------------------------------------------------------------------

    /** Отменить подписку: сразу (доступ закрывается) или по окончании периода. */
    public function cancel(bool $immediately = false): self
    {
        return app(SubscriptionManager::class)->cancel($this, $immediately);
    }

    /** Продлить на тот же период (списание с кошелька или счёт на оплату). */
    public function renew(): \PortalConnect\Subscriptions\DTO\PurchaseOutcome
    {
        return app(SubscriptionManager::class)->renew($this);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)->where('ends_at', '>', now());
    }

    public function scopeEnded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }
}
