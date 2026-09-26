<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_variant_id', 'product_name', 'product_image_url', 'color', 'size', 'price', 'quantity', 'subtotal'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
