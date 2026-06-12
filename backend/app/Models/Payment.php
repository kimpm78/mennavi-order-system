<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'user_payment_method_id',
        'provider',
        'provider_customer_id',
        'provider_card_id',
        'provider_charge_id',
        'payment_method',
        'payment_status',
        'amount',
        'currency',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
        'provider_response',
        'paid_at',
        'failed_at',
        'refunded_at',
    ];

    protected $casts = [
        'card_exp_month' => 'integer',
        'card_exp_year' => 'integer',
        'provider_response' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
