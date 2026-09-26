<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Validation\ValidationException;

class OrderStateMachine
{
    public function __construct(private ?ShippingStateMachine $shippingStateMachine = null) {}

    public const ORDER_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping' => ['completed', 'delivery_failed', 'returning', 'returned'],
        'delivery_failed' => ['shipping', 'returning', 'returned'],
        'returning' => ['returned'],
        'completed' => [],
        'cancelled' => [],
        'returned' => [],
    ];

    public const PAYMENT_TRANSITIONS = [
        'unpaid' => ['pending', 'paid', 'failed'],
        'pending' => ['paid', 'failed'],
        'failed' => ['pending'],
        'paid' => ['refund_pending', 'refunded'],
        'refund_pending' => ['refunded'],
        'refunded' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return $from === $to || in_array($to, self::ORDER_TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> */
    public function allowedOrderTransitions(string $from): array
    {
        return self::ORDER_TRANSITIONS[$from] ?? [];
    }

    /** @return list<string> */
    public function allowedOrderTransitionsFor(Order $order): array
    {
        $allowed = $this->allowedOrderTransitions((string) $order->status);
        if ($order->payment_method !== 'cod' && $order->payment_status !== 'paid') {
            $blocked = ['confirmed', 'shipping', 'completed'];
            if ($order->payment_status === 'pending') {
                $blocked[] = 'cancelled';
            }
            $allowed = array_values(array_diff($allowed, $blocked));
        }

        return $allowed;
    }

    public function canTransitionFor(Order $order, string $target): bool
    {
        return $order->status === $target || in_array($target, $this->allowedOrderTransitionsFor($order), true);
    }

    public function canTransitionPayment(string $from, string $to): bool
    {
        return $from === $to || in_array($to, self::PAYMENT_TRANSITIONS[$from] ?? [], true);
    }

    public function transition(
        Order $order,
        string $target,
        string $source,
        ?int $userId = null,
        ?string $note = null,
        array $attributes = [],
    ): bool {
        if ($order->status === $target) {
            return false;
        }

        if (! $this->canTransition($order->status, $target)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển đơn hàng từ {$order->status} sang {$target}.",
            ]);
        }

        $shippingStateMachine = $this->shippingStateMachine ??= new ShippingStateMachine;
        $requestedShippingStatus = (string) ($attributes['shipping_status'] ?? $this->shippingStatusFor($target));
        $canonicalShippingStatus = $shippingStateMachine->normalize($requestedShippingStatus);
        if ($canonicalShippingStatus === null
            || ! in_array($canonicalShippingStatus, $this->shippingStatusesForOrder($target), true)
            || ! $shippingStateMachine->canReach((string) $order->shipping_status, $canonicalShippingStatus)) {
            throw ValidationException::withMessages([
                'shipping_status' => "Trạng thái vận chuyển {$requestedShippingStatus} không phù hợp với đơn hàng {$target}.",
            ]);
        }

        $attributes['status'] = $target;
        $attributes['shipping_status'] = $canonicalShippingStatus;
        if ($target === 'completed') {
            $attributes['completed_at'] = $order->completed_at ?? now();
        }

        $order->update($attributes);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $target,
            'source' => $source,
            'changed_by_user_id' => $userId,
            'note' => $note,
        ]);

        return true;
    }

    public function transitionPayment(Order $order, string $target, array $attributes = []): bool
    {
        if ($order->payment_status === $target) {
            return false;
        }

        if (! $this->canTransitionPayment($order->payment_status, $target)) {
            throw ValidationException::withMessages([
                'payment_status' => "Không thể chuyển thanh toán từ {$order->payment_status} sang {$target}.",
            ]);
        }

        $order->update(array_merge($attributes, ['payment_status' => $target]));

        return true;
    }

    /** @return list<string> */
    private function shippingStatusesForOrder(string $status): array
    {
        return match ($status) {
            'pending' => ['pending'],
            'confirmed' => ['ready_to_pick'],
            'shipping' => ['shipping', 'partial_return'],
            'completed' => ['delivered'],
            'delivery_failed' => ['delivery_fail'],
            'returning' => ['returning'],
            'returned' => ['returned'],
            'cancelled' => ['cancelled'],
            default => [],
        };
    }

    private function shippingStatusFor(string $status): string
    {
        return match ($status) {
            'pending' => 'pending',
            'confirmed' => 'ready_to_pick',
            'shipping' => 'shipping',
            'completed' => 'delivered',
            'delivery_failed' => 'delivery_fail',
            'returning' => 'return_transporting',
            'returned' => 'returned',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }
}
