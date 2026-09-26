<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $fillable = ['order_id', 'status', 'source', 'changed_by_user_id', 'note'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
