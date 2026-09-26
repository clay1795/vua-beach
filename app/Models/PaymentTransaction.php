<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'Đang khởi tạo',
        'initiated' => 'Chờ khách thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Không thành công',
        'cancelled' => 'Đã hủy',
        'refund_pending' => 'Chờ hoàn tiền',
        'refunded' => 'Đã hoàn tiền',
    ];

    protected $fillable = [
        'order_id',
        'parent_transaction_id',
        'type',
        'gateway',
        'gateway_order_id',
        'gateway_request_id',
        'transaction_id',
        'amount',
        'status',
        'result_code',
        'response_code',
        'bank_code',
        'message',
        'request_payload',
        'response_payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Đang cập nhật';
    }
}
