<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'is_cover', 'sort_order'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
