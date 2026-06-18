<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSubscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'started_at',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'ended_at',
        'provider',
        'provider_customer_id',
        'provider_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    // 契約ユーザーを取得
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 契約中のプランを取得
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    // サブスクリプションの決済履歴を取得
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    // 現在利用可能な契約か判定
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->current_period_start?->lte(now())
            && $this->current_period_end?->gt(now());
    }
}