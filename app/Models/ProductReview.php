<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $fillable = ['user_id', 'product_id', 'order_id', 'rating', 'content', 'is_visible'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
