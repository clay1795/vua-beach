<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    public const ELIGIBILITY_DAYS = 7;

    public const ACTIVE_STATUSES = ['requested', 'approved', 'received'];

    protected $fillable = [
        'order_id', 'user_id', 'type', 'status', 'reason', 'customer_note', 'admin_note',
        'refund_amount', 'refund_processed_at', 'approved_at', 'received_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['refund_processed_at' => 'datetime', 'approved_at' => 'datetime', 'received_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function histories()
    {
        return $this->hasMany(ReturnRequestHistory::class)->latest();
    }

    public function canBeRequested(): bool
    {
        return $this->order?->completed_at?->greaterThanOrEqualTo(now()->subDays(self::ELIGIBILITY_DAYS)) ?? false;
    }
}
