<?php

namespace App\Services;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GHNOrderSyncService
{
    public function __construct(
        private readonly GHNService $ghn,
        private readonly ShippingStateMachine $shippingStateMachine,
    ) {}

    /**
     * Apply a GHN shipping status and keep the Vua Beach order lifecycle in sync.
     *
     * @return array{shipping_status:string, order_status:?string, order_changed:bool}
     */
    public function apply(Order $order, string $ghnStatus): array
    {
        $shippingStatus = $this->ghn->normalizeShippingStatus($ghnStatus);

        $result = DB::transaction(function () use ($order, $ghnStatus, $shippingStatus): array {
            $locked = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            $oldStatus = $locked->status;
            if ($shippingStatus === null) {
                Log::channel('ghn')->warning('Bỏ qua trạng thái GHN không được hỗ trợ.', [
                    'order_id' => $locked->id,
                    'ghn_status' => $ghnStatus,
                ]);

                return [
                    'shipping_status' => $locked->shipping_status,
                    'order_status' => $oldStatus,
                    'order_changed' => false,
                ];
            }
            if (in_array($oldStatus, ['completed', 'cancelled', 'returned'], true)) {
                return [
                    'shipping_status' => $locked->shipping_status,
                    'order_status' => $oldStatus,
                    'order_changed' => false,
                ];
            }
            if ($this->shippingStateMachine->normalize((string) $locked->shipping_status) === 'partial_return') {
                Log::channel('ghn')->warning('Đơn hoàn một phần đang chờ đối soát; không tự động ghi đè trạng thái.', [
                    'order_id' => $locked->id,
                    'ghn_status' => $ghnStatus,
                ]);

                return [
                    'shipping_status' => $locked->shipping_status,
                    'order_status' => $oldStatus,
                    'order_changed' => false,
                ];
            }
            if (! $this->shippingStateMachine->canReach((string) $locked->shipping_status, $shippingStatus)) {
                Log::channel('ghn')->notice('Bỏ qua trạng thái GHN cũ hoặc không hợp lệ.', [
                    'order_id' => $locked->id,
                    'from' => $locked->shipping_status,
                    'to' => $shippingStatus,
                ]);

                return [
                    'shipping_status' => $locked->shipping_status,
                    'order_status' => $oldStatus,
                    'order_changed' => false,
                ];
            }
            $newStatus = $this->orderStatusFor($shippingStatus, $oldStatus);

            if (in_array($newStatus, ['cancelled', 'returned'], true) && ! in_array($oldStatus, ['cancelled', 'returned'], true)) {
                $this->restoreReservedStock($locked);
            }

            $orderChanged = false;
            if ($newStatus !== null && $newStatus !== $oldStatus) {
                foreach ($this->transitionPath($locked->status, $newStatus) as $step) {
                    $attributes = $step === $newStatus ? ['shipping_status' => $shippingStatus] : [];
                    $orderChanged = app(OrderStateMachine::class)->transition($locked, $step, 'ghn', null, "GHN cập nhật vận đơn sang trạng thái {$ghnStatus}.", $attributes) || $orderChanged;
                    if ($step === 'completed' && $locked->payment_method === 'cod') {
                        app(OrderStateMachine::class)->transitionPayment($locked, 'paid');
                    }
                    $locked->refresh();
                }
            } elseif ($locked->shipping_status !== $shippingStatus) {
                $this->shippingStateMachine->advance($locked, $shippingStatus);
            }

            $locked->refresh();

            return [
                'shipping_status' => $locked->shipping_status,
                'order_status' => $locked->status,
                'order_changed' => $orderChanged,
            ];
        });

        if ($result['order_changed']) {
            try {
                Mail::to($order->email)->queue(new OrderStatusUpdatedMail($order->fresh()));
            } catch (\Throwable $exception) {
                app(MailFailureAlert::class)->report('mail_enqueue_failed', OrderStatusUpdatedMail::class, $exception, ['order_id' => $order->id]);
            }
        }

        return $result;
    }

    /**
     * A GHN partial return does not identify which order items came back. Keep the
     * aggregate order and inventory unchanged until an administrator reconciles it.
     *
     * @return array{shipping_status:string, order_status:string, order_changed:bool, requires_manual_review:bool}
     */
    public function applyPartialReturn(Order $order, string $ghnStatus, ?string $partialReturnCode = null): array
    {
        return DB::transaction(function () use ($order, $ghnStatus, $partialReturnCode): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $currentShipping = $this->shippingStateMachine->normalize((string) $locked->shipping_status);
            if (! in_array($locked->status, ['completed', 'cancelled', 'returned'], true)
                && in_array($currentShipping, ['shipping', 'delivery_fail', 'partial_return'], true)) {
                $this->shippingStateMachine->advance($locked, 'partial_return');
            }

            Log::channel('ghn')->warning('GHN báo hoàn một phần; cần đối soát thủ công, chưa thay đổi tồn kho.', [
                'order_id' => $locked->id,
                'ghn_status' => $ghnStatus,
                'partial_return_code' => $partialReturnCode,
            ]);

            return [
                'shipping_status' => $locked->shipping_status,
                'order_status' => $locked->status,
                'order_changed' => false,
                'requires_manual_review' => true,
            ];
        });
    }

    private function orderStatusFor(string $shippingStatus, string $currentStatus): ?string
    {
        if (in_array($currentStatus, ['completed', 'cancelled', 'returned'], true)) {
            return null;
        }

        return match ($shippingStatus) {
            'ready_to_pick' => 'confirmed',
            'shipping' => 'shipping',
            'delivery_fail' => 'delivery_failed',
            'returning' => 'returning',
            'delivered' => 'completed',
            'cancelled' => 'cancelled',
            'returned' => 'returned',
            default => null,
        };
    }

    private function restoreReservedStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->product_variant_id) {
                continue;
            }
            app(InventoryService::class)->applyOnce($item->product_variant_id, $item->quantity, "order:{$order->id}:variant:{$item->product_variant_id}:release", 'ghn_cancelled_or_returned', $order->id, 'Hoàn tồn kho do GHN hủy/hoàn vận đơn '.$order->order_code);
        }
    }

    /** @return list<string> */
    private function transitionPath(string $from, string $to): array
    {
        if ($from === $to) {
            return [];
        }

        $queue = [[$from, []]];
        $visited = [$from => true];
        while ($queue !== []) {
            [$status, $path] = array_shift($queue);
            foreach (OrderStateMachine::ORDER_TRANSITIONS[$status] ?? [] as $next) {
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
}
