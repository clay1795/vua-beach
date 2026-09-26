<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderShipmentService
{
    /**
     * @return array{status:string,order_code?:string,message?:string}
     */
    public function create(Order $order, GHNService $ghn, string $source): array
    {
        if (! $ghn->configured()) {
            return ['status' => 'unavailable', 'message' => 'GHN chưa được cấu hình.'];
        }

        try {
            return Cache::lock('ghn-create-order:'.$order->id, 45)->block(10, function () use ($order, $ghn, $source): array {
                $fresh = Order::query()->with('items')->findOrFail($order->id);
                if (filled($fresh->ghn_order_code)) {
                    return ['status' => 'already_created', 'order_code' => $fresh->ghn_order_code];
                }
                if ($fresh->payment_method !== 'cod' && $fresh->payment_status !== 'paid') {
                    return ['status' => 'not_ready', 'message' => 'Đơn online chưa được xác nhận thanh toán.'];
                }

                $response = $ghn->createOrder($fresh);
                $ghnOrderCode = (string) ($response['data']['order_code'] ?? '');
                if (($response['code'] ?? 0) !== 200 || $ghnOrderCode === '') {
                    Log::channel('ghn')->warning('Không thể tạo vận đơn cho đơn hàng.', [
                        'order_id' => $fresh->id,
                        'response_code' => $response['code'] ?? null,
                    ]);

                    return ['status' => 'failed', 'message' => $response['message'] ?? 'GHN chưa thể tạo vận đơn.'];
                }

                return DB::transaction(function () use ($fresh, $ghnOrderCode, $source): array {
                    $locked = Order::query()->lockForUpdate()->findOrFail($fresh->id);
                    if (filled($locked->ghn_order_code)) {
                        return ['status' => 'already_created', 'order_code' => $locked->ghn_order_code];
                    }
                    if ($locked->status === 'pending') {
                        app(OrderStateMachine::class)->transition(
                            $locked,
                            'confirmed',
                            $source,
                            null,
                            'Đã tạo vận đơn GHN '.$ghnOrderCode.'.',
                            ['ghn_order_code' => $ghnOrderCode],
                        );
                    } else {
                        $locked->update(['ghn_order_code' => $ghnOrderCode]);
                    }

                    return ['status' => 'created', 'order_code' => $ghnOrderCode];
                });
            });
        } catch (LockTimeoutException) {
            return ['status' => 'busy', 'message' => 'Đơn hàng đang được tạo vận đơn ở yêu cầu khác.'];
        }
    }
}
