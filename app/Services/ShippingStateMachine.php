<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

class ShippingStateMachine
{
    public const TRANSITIONS = [
        'pending' => ['ready_to_pick', 'cancelled'],
        'ready_to_pick' => ['shipping', 'cancelled'],
        'shipping' => ['delivered', 'delivery_fail', 'returning', 'partial_return'],
        'delivery_fail' => ['shipping', 'returning', 'partial_return'],
        // Chỉ thao tác đối soát thủ công của admin mới được rời trạng thái này;
        // GHNOrderSyncService chủ động khóa mọi webhook tự động đến sau.
        'partial_return' => ['delivered', 'returning'],
        'returning' => ['returned'],
        'delivered' => [],
        'returned' => [],
        'cancelled' => [],
    ];

    public function normalize(string $providerStatus): ?string
    {
        return match (strtolower(trim($providerStatus))) {
            'pending' => 'pending',
            'ready_to_pick', 'picking', 'money_collect_picking' => 'ready_to_pick',
            'shipping', 'picked', 'storing', 'transporting', 'sorting', 'delivering',
            'money_collect_delivering' => 'shipping',
            'delivery_fail' => 'delivery_fail',
            'return', 'returning', 'waiting_to_return', 'return_transporting' => 'returning',
            'delivered' => 'delivered',
            'cancel', 'cancelled' => 'cancelled',
            'returned' => 'returned',
            'partial_return' => 'partial_return',
            default => null,
        };
    }

    public function canTransition(string $from, string $to): bool
    {
        $from = $this->normalize($from) ?? $from;
        $to = $this->normalize($to) ?? $to;

        return $from === $to || in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function canReach(string $from, string $to): bool
    {
        $from = $this->normalize($from) ?? $from;
        $to = $this->normalize($to) ?? $to;

        return $from === $to || $this->transitionPath($from, $to) !== [];
    }

    /** @return list<string> */
    public function transitionPath(string $from, string $to): array
    {
        $from = $this->normalize($from) ?? $from;
        $to = $this->normalize($to) ?? $to;
        if ($from === $to || ! isset(self::TRANSITIONS[$from], self::TRANSITIONS[$to])) {
            return [];
        }

        $queue = [[$from, []]];
        $visited = [$from => true];
        while ($queue !== []) {
            [$status, $path] = array_shift($queue);
            foreach (self::TRANSITIONS[$status] as $next) {
                if (isset($visited[$next])) {
                    continue;
                }
                $nextPath = [...$path, $next];
                if ($next === $to) {
                    return $nextPath;
                }
                $visited[$next] = true;
                $queue[] = [$next, $nextPath];
            }
        }

        return [];
    }

    public function advance(Order $order, string $target): bool
    {
        $current = $this->normalize((string) $order->shipping_status);
        $target = $this->normalize($target);
        if ($current === null || $target === null || ! $this->canReach($current, $target)) {
            throw ValidationException::withMessages([
                'shipping_status' => "Không thể chuyển vận chuyển từ {$order->shipping_status} sang ".($target ?? 'không xác định').'.',
            ]);
        }
        if ($current === $target && $order->shipping_status === $target) {
            return false;
        }

        $order->update(['shipping_status' => $target]);

        return true;
    }
}
