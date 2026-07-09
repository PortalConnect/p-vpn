<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAIL = 'fail';
    public const STATUS_REFUNDED = 'refunded';

    public const INTENT_WALLET_TOPUP = 'wallet_topup';
    public const INTENT_SUBSCRIPTION_PURCHASE = 'subscription_purchase';

    protected $fillable = [
        'user_id',
        'provider',
        'external_id',
        'provider_operation_id',
        'cardlink_bill_id',
        'cardlink_payment_id',
        'pay_url',
        'amount_kopecks',
        'status',
        'intent',
        'intent_subscription_id',
        'paid_at',
        'raw_payload',
    ];

    protected $casts = [
        'amount_kopecks' => 'integer',
        'paid_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intentSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'intent_subscription_id');
    }
}
