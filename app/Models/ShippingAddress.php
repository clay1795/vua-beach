<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['label', 'recipient_name', 'phone', 'address', 'province_id', 'province_name', 'district_id', 'district_name', 'ward_code', 'ward_name', 'is_default'])]
class ShippingAddress extends Model
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
