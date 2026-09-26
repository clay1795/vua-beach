<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;

class CouponRedemptionService
{
    /** Call inside the transaction that finalizes a successfully placed/paid order. */
    public function consumeForOrder(Order $order): void
    {
        if (blank($order->coupon_code) || CouponUsage::where('order_id', $order->id)->exists()) {
            return;
        }

        $coupon = Coupon::lockForUpdate()->where('code', $order->coupon_code)->first();
        if (! $coupon) {
            return;
        }

        CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $order->user_id, 'order_id' => $order->id]);
        $coupon->increment('used_count');
    }
}
