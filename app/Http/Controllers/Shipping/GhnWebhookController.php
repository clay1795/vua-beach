<?php

namespace App\Http\Controllers\Shipping;

use App\Exceptions\WebhookConflictException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\GHNOrderSyncService;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GhnWebhookController extends Controller
{
    public function __invoke(Request $request, GHNOrderSyncService $sync): JsonResponse
    {
        $secret = (string) config('services.ghn.webhook_secret');
        $providedSecret = (string) ($request->header('X-GHN-Webhook-Secret') ?: $request->query('secret', ''));
        if ($secret === '' || $providedSecret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'OrderCode' => ['required', 'string', 'max:100'],
            'ClientOrderCode' => ['nullable', 'string', 'max:100'],
            'Status' => ['required', 'string', 'max:60'],
            'Type' => ['nullable', 'string', 'max:60'],
            'Time' => ['nullable', 'string', 'max:80'],
            'ShopID' => ['required', 'integer'],
            'IsPartialReturn' => ['sometimes', 'boolean'],
            'PartialReturnCode' => ['nullable', 'string', 'max:100'],
        ]);

        if ((string) $data['ShopID'] !== (string) config('services.ghn.shop_id')) {
            return response()->json(['message' => 'Invalid shop'], 403);
        }

        $order = Order::query()->where('ghn_order_code', $data['OrderCode'])->first();
        if (! $order && filled($data['ClientOrderCode'] ?? null)) {
            $order = Order::query()->where('order_code', $data['ClientOrderCode'])->first();
        }
        if (! $order || (filled($order->ghn_order_code) && $order->ghn_order_code !== $data['OrderCode'])) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $eventKey = implode(':', [
            $data['OrderCode'],
            $data['Status'],
            $data['Type'] ?? 'none',
            $data['Time'] ?? 'none',
            ($data['IsPartialReturn'] ?? false) ? 'partial' : 'full',
            $data['PartialReturnCode'] ?? 'none',
        ]);

        try {
            $result = app(WebhookIdempotencyService::class)->process('ghn', $eventKey, $request->except('secret'), function () use ($order, $data, $sync) {
                if (blank($order->ghn_order_code)) {
                    $order->update(['ghn_order_code' => $data['OrderCode']]);
                }
                $synced = ($data['IsPartialReturn'] ?? false)
                    ? $sync->applyPartialReturn($order, $data['Status'], $data['PartialReturnCode'] ?? null)
                    : $sync->apply($order, $data['Status']);

                return ['code' => 200, 'body' => [
                    'message' => 'OK',
                    'shipping_status' => $synced['shipping_status'],
                    'requires_manual_review' => $synced['requires_manual_review'] ?? false,
                ]];
            });
        } catch (WebhookConflictException) {
            Log::channel('ghn')->warning('GHN webhook event key conflict.', [
                'order_id' => $order->id,
                'event_fingerprint' => substr(hash('sha256', $eventKey), 0, 16),
            ]);

            return response()->json(['message' => 'Conflicting event'], 409);
        }

        Log::channel('ghn')->info('GHN webhook handled.', [
            'order_id' => $order->id,
            'status' => $data['Status'],
            'type' => $data['Type'] ?? null,
            'replayed' => $result['replayed'],
        ]);

        return response()->json($result['body'], $result['code']);
    }
}
