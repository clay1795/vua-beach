<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    protected $fillable = ['receipt_code', 'supplier_id', 'created_by_user_id', 'received_at', 'total_cost', 'note'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
}
