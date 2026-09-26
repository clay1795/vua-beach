<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_variant_id', 'order_id', 'purchase_receipt_id', 'type', 'quantity', 'balance_after', 'reason', 'idempotency_key', 'note',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }
}
