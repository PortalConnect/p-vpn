<?php

namespace PortalConnect\Subscriptions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Тарифный план (в духе laravelcm/laravel-subscriptions, без usage-фич):
 * период в месяцах + цена в копейках + произвольные features для витрины.
 */
class Plan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = [
        'slug',
        'name',
        'months',
        'price_kopecks',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'months' => 'integer',
        'price_kopecks' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(config('subscriptions.model', Subscription::class));
    }

    public function isFree(): bool
    {
        return $this->price_kopecks === 0;
    }

    public function pricePerMonthKopecks(): int
    {
        return $this->months > 0 ? intdiv($this->price_kopecks, $this->months) : $this->price_kopecks;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
