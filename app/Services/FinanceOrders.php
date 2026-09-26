<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinanceOrders
{
    public const PRIORITY = "CASE WHEN status IN ('paid', 'refund_pending', 'refunded') THEN 0 ELSE 1 END";

    public function query(): Builder
    {
        $payment = DB::table('payment_transactions')->select('id')
            ->whereColumn('order_id', 'orders.id')->where('type', 'payment')
            ->orderByRaw(self::PRIORITY)->orderByDesc('id')->limit(1);
        $source = DB::table('orders')->leftJoin('payment_transactions as payment', function ($join) use ($payment) {
            $join->on('payment.order_id', '=', 'orders.id')->where('payment.id', '=', $payment);
        })->select('orders.*', 'payment.id as payment_id', 'payment.paid_at')
            ->selectRaw('COALESCE(payment.gateway, orders.payment_method) as gateway')
            ->selectRaw("CASE WHEN orders.payment_status IN ('refund_pending', 'refunded') THEN orders.payment_status WHEN orders.payment_method = 'cod' AND orders.payment_status = 'paid' AND COALESCE(payment.status, 'pending') NOT IN ('refund_pending', 'refunded') THEN 'paid' ELSE COALESCE(payment.status, orders.payment_status) END as finance_status");

        return DB::query()->fromSub($source, 'finance_orders')->where('created_at', '<=', now());
    }

    public function paid(): Builder
    {
        return $this->query()->where('finance_status', 'paid')->where('refunded_amount', 0)
            ->whereNotIn('status', ['cancelled', 'returning', 'returned'])
            ->whereNotIn('shipping_status', ['cancelled', 'return', 'returning', 'return_transporting', 'returned', 'partial_return']);
    }
}
