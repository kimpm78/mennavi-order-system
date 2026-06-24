<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'store_name_snapshot',
        'store_invoice_number',
        'receipt_type',
        'subtotal_amount',
        'delivery_fee',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'earned_points',
        'order_status',
        'cooking_started_at',
        'payment_method',
        'payment_status',
        'delivery_staff_name',
        'note',
        'ordered_at',
        'delivered_at',
        'received_at',
        'user_subscription_id',
        'membership_discount_rate',
        'membership_discount_amount',
        'delivery_discount_amount',
        'applied_subscription_code',
    ];

    protected $casts = [
            'tax_rate' => 'decimal:2',
            'earned_points' => 'integer',
            'ordered_at' => 'datetime',
            'cooking_started_at' => 'datetime',
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
        ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // 注文時に適用されたサブスクリプションを取得
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            UserSubscription::class,
            'user_subscription_id',
        );
    }
}
