<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'color', 'size', 'stock', 'low_stock_threshold', 'is_active'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_variant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
