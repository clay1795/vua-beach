<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\GHNOrderSyncService;
use App\Services\GHNService;
use Illuminate\Console\Command;

class SyncGhnOrders extends Command
{
    protected $signature = 'ghn:sync-orders {--limit=50 : Số đơn GHN tối đa cần đồng bộ}';

    protected $description = 'Đồng bộ trạng thái vận đơn GHN đang giao về Vua Beach';

    public function handle(GHNService $ghn, GHNOrderSyncService $syncService): int
    {
        if (! $ghn->configured()) {
            $this->error('GHN chưa được cấu hình.');

            return self::FAILURE;
        }

        $orders = Order::query()
            ->whereNotNull('ghn_order_code')
            ->whereIn('status', ['confirmed', 'shipping'])
            ->orderBy('updated_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($orders as $order) {
            $result = $ghn->orderDetail($order->ghn_order_code);
            $details = $result['data'] ?? [];
            if (isset($details[0]) && is_array($details[0])) {
                $details = $details[0];
            }

            if (($result['code'] ?? 0) !== 200 || blank($details['status'] ?? null)) {
                $this->warn("Không thể đồng bộ {$order->order_code}.");

                continue;
            }

            $synced = $syncService->apply($order, (string) $details['status']);
            $message = "Đã đồng bộ {$order->order_code}: {$synced['shipping_status']}";
            if ($synced['order_changed']) {
                $message .= " → đơn hàng {$synced['order_status']}";
            }
            $this->line($message.'.');
        }

        $this->info("Đã kiểm tra {$orders->count()} vận đơn GHN.");

        return self::SUCCESS;
    }
}
