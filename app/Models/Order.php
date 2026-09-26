<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'shipping' => 'Đang giao',
        'delivery_failed' => 'Giao hàng thất bại',
        'returning' => 'Đang hoàn về',
        'returned' => 'Đã hoàn về',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'unpaid' => 'Chưa thanh toán',
        'pending' => 'Đang chờ thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán thất bại',
        'refund_pending' => 'Chờ xác nhận hoàn tiền',
        'refunded' => 'Đã hoàn tiền',
    ];

    public const SHIPPING_STATUS_LABELS = [
        'pending' => 'Chờ bàn giao',
        'ready_to_pick' => 'Chờ lấy hàng',
        'shipping' => 'Đang giao',
        'delivered' => 'Đã giao',
        'delivery_fail' => 'Giao hàng thất bại',
        'returning' => 'Đang hoàn về',
        'return_transporting' => 'Đang hoàn về',
        'returned' => 'Đã hoàn về',
        'partial_return' => 'Hoàn một phần — chờ đối soát',
        'cancelled' => 'Đã hủy',
    ];

    protected $fillable = ['user_id', 'order_code', 'customer_name', 'email', 'phone', 'address', 'to_district_id', 'to_ward_code', 'note', 'coupon_code', 'subtotal_amount', 'discount_amount', 'refunded_amount', 'shipping_fee', 'total_amount', 'payment_method', 'payment_status', 'status', 'completed_at', 'shipping_status', 'ghn_order_code', 'vnpay_transaction_no', 'vnpay_bank_code', 'vnpay_response_code', 'vnpay_paid_at', 'momo_request_id', 'momo_trans_id', 'momo_result_code', 'momo_payment_status', 'momo_paid_at'];

    protected function casts(): array
    {
        return ['vnpay_paid_at' => 'datetime', 'momo_paid_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function latestPaymentTransaction()
    {
        return $this->hasOne(PaymentTransaction::class)->latestOfMany();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Đang cập nhật';
    }

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? 'Đang cập nhật';
    }

    public function shippingStatusLabel(): string
    {
        return self::SHIPPING_STATUS_LABELS[$this->shipping_status] ?? 'Đang cập nhật';
    }
}
