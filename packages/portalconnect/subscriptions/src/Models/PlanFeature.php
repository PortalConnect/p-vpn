<?php

namespace PortalConnect\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Возможность плана: value NULL — безлимит, число — квота на период подписки. */
class PlanFeature extends Model
{
    protected $table = 'subscription_plan_features';

    protected $fillable = ['plan_id', 'slug', 'name', 'value', 'sort_order'];

    protected $casts = [
        'value' => 'integer',
        'sort_order' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function isUnlimited(): bool
    {
        return $this->value === null;
    }
}
