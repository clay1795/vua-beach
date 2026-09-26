<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptItem extends Model
{
    protected $fillable = ['purchase_receipt_id', 'product_variant_id', 'quantity', 'unit_cost', 'line_total'];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
