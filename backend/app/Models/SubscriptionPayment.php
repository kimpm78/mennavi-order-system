<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_subscription_id',
        'user_payment_method_id',
        'provider',
        'provider_payment_id',
        'amount',
        'currency',
        'payment_status',
        'period_start',
        'period_end',
        'paid_at',
        'failed_at',
        'refunded_at',
        'provider_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    // 契約情報を取得
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            UserSubscription::class,
            'user_subscription_id',
        );
    }

    // 使用した決済方法を取得
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(
            UserPaymentMethod::class,
            'user_payment_method_id',
        );
    }
}