<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'name', 'scope', 'type', 'value', 'min_order_amount', 'max_discount_amount',
        'usage_limit', 'per_user_limit', 'used_count', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isUsableFor(int $subtotal): bool
    {
        return $this->is_active
            && $subtotal >= $this->min_order_amount
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->ends_at || $this->ends_at->isFuture())
            && (! $this->usage_limit || $this->used_count < $this->usage_limit);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function discountFor(int $subtotal): int
    {
        $discount = $this->type === 'percent'
            ? (int) floor($subtotal * $this->value / 100)
            : $this->value;

        if ($this->max_discount_amount) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return min($subtotal, max(0, $discount));
    }
}
