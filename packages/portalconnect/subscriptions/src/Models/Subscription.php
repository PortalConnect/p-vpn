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
        'tag',
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
        'trial_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
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

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function usage(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SubscriptionUsage::class, 'subscription_id');
    }

    /** Можно ли использовать фичу (есть в плане и лимит не исчерпан). */
    public function canUseFeature(string $slug): bool
    {
        $feature = $this->plan?->feature($slug);
        if (!$feature) {
            return false;
        }
        if ($feature->isUnlimited()) {
            return true;
        }

        return $this->getFeatureUsage($slug) < $feature->value;
    }

    public function getFeatureUsage(string $slug): int
    {
        return (int) $this->usage()->where('feature_slug', $slug)->value('used');
    }

    /** Остаток квоты; null — безлимит; 0 — исчерпано или фичи нет. */
    public function getFeatureRemainings(string $slug): ?int
    {
        $feature = $this->plan?->feature($slug);
        if (!$feature) {
            return 0;
        }
        if ($feature->isUnlimited()) {
            return null;
        }

        return max(0, $feature->value - $this->getFeatureUsage($slug));
    }

    /** Записать использование; false — лимит не позволяет. */
    public function recordFeatureUsage(string $slug, int $quantity = 1): bool
    {
        $feature = $this->plan?->feature($slug);
        if (!$feature) {
            return false;
        }
        if (!$feature->isUnlimited() && $this->getFeatureUsage($slug) + $quantity > $feature->value) {
            return false;
        }

        $usage = $this->usage()->firstOrCreate(['feature_slug' => $slug], ['used' => 0]);
        $usage->increment('used', $quantity);

        return true;
    }

    public function reduceFeatureUsage(string $slug, int $quantity = 1): void
    {
        $usage = $this->usage()->where('feature_slug', $slug)->first();
        if ($usage) {
            $usage->update(['used' => max(0, $usage->used - $quantity)]);
        }
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
