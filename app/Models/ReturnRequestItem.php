<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequestItem extends Model
{
    protected $fillable = ['return_request_id', 'order_item_id', 'quantity', 'desired_size', 'replacement_variant_id'];

    public function request()
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function replacementVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'replacement_variant_id');
    }
}
