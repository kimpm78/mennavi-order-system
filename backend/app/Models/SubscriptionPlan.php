<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'price',
        'currency',
        'billing_cycle',
        'discount_rate',
        'free_delivery',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'discount_rate' => 'decimal:2',
            'free_delivery' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // このプランを契約しているユーザー情報を取得
    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }
}