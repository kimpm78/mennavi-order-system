<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'address',
        'phone',
        'image_path',
        'rating',
        'review_count',
        'budget_label',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'review_count' => 'integer',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
