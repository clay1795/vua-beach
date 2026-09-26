<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GHNService
{
    public function configured(): bool
    {
        return filter_var(config('services.ghn.enabled'), FILTER_VALIDATE_BOOLEAN)
            && filled(config('services.ghn.token'))
            && filled(config('services.ghn.shop_id'))
            && filled(config('services.ghn.from_district_id'));
    }

    public function provinces(): array
    {
        $response = $this->get('/master-data/province');

        if (($response['code'] ?? 500) === 200 && is_array($response['data'] ?? null)) {
            // The GHN test environment exposes non-serviceable test records.
            // Do not offer them to shoppers because they have no usable districts.
            $response['data'] = array_values(array_filter(
                $response['data'],
                fn (array $province) => ! in_array($province['ProvinceName'] ?? '', ['Hà Nội 02', 'Test - Alert - Tỉnh - 001'], true),
            ));
        }

        return $response;
    }

    public function districts(int $provinceId): array
    {
        return $this->get('/master-data/district', ['province_id' => $provinceId]);
    }

    public function wards(int $districtId): array
    {
        return $this->get('/master-data/ward', ['district_id' => $districtId]);
    }

    public function calculateFee(int $districtId, string $wardCode, int $weight = 300): array
    {
        return $this->post('/v2/shipping-order/fee', [
            'shop_id' => (int) config('services.ghn.shop_id'),
            'from_district_id' => (int) config('services.ghn.from_district_id'),
            'to_district_id' => $districtId,
            'to_ward_code' => $wardCode,
            'service_type_id' => 2,
            'weight' => max($weight, 300),
            'length' => 20,
            'width' => 15,
            'height' => 10,
        ]);
    }

    /**
     * Create a GHN shipment from a confirmed Vua Beach order.
     * The GHN Shop ID provides the sender/pickup information configured in GHN.
     */
    public function createOrder(Order $order): array
    {
        if (! $this->configured()) {
            return ['code' => 503, 'message' => 'GHN chưa được cấu hình.'];
        }

        if (! $order->to_district_id || ! $order->to_ward_code) {
            return ['code' => 422, 'message' => 'Đơn hàng chưa có đủ Quận/Huyện và Phường/Xã GHN.'];
        }

        $order->loadMissing('items');
        $weight = max(300, $order->items->sum(fn ($item) => 200 * $item->quantity));

        return $this->post('/v2/shipping-order/create', [
            'shop_id' => (int) config('services.ghn.shop_id'),
            // ShopID supplies the sender and pickup address configured in GHN.
            'client_order_code' => $order->order_code,
            'to_name' => $order->customer_name,
            'to_phone' => $order->phone,
            'to_address' => $order->address,
            'to_district_id' => (int) $order->to_district_id,
            'to_ward_code' => (string) $order->to_ward_code,
            'payment_type_id' => 2,
            'cod_amount' => $order->payment_method === 'cod' ? (int) $order->subtotal_amount : 0,
            'content' => 'Đơn hàng Vua Beach: '.$order->order_code,
            'note' => (string) ($order->note ?? ''),
            // Required by GHN: customer may inspect the parcel but cannot try it on.
            'required_note' => 'CHOXEMHANGKHONGTHU',
            'weight' => $weight,
            'length' => 20,
            'width' => 15,
            'height' => 10,
            'service_type_id' => 2,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'code' => 'VB-'.$item->id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'length' => 20,
                'width' => 15,
                'height' => 10,
                'weight' => max(200, 200 * $item->quantity),
                'category' => ['level1' => 'Đồ bơi'],
            ])->values()->all(),
        ]);
    }

    public function orderDetail(string $orderCode): array
    {
        return $this->post('/v2/shipping-order/detail', ['order_code' => $orderCode]);
    }

    public function normalizeShippingStatus(string $status): ?string
    {
        return app(ShippingStateMachine::class)->normalize($status);
    }

    private function client()
    {
        return Http::baseUrl((string) config('services.ghn.base_url'))
            ->acceptJson()
            ->connectTimeout(8)
            ->timeout(20)
            // GHN dev can time out over IPv6 on some local networks. Force IPv4
            // so the checkout location selectors remain reliable on macOS/XAMPP.
            ->withOptions([
                'verify' => filter_var(config('services.ghn.verify_ssl'), FILTER_VALIDATE_BOOLEAN),
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4],
            ])
            ->withHeaders(['Token' => config('services.ghn.token'), 'ShopId' => config('services.ghn.shop_id')]);
    }

    private function get(string $uri, array $query = []): array
    {
        if (! $this->configured()) {
            return ['code' => 503, 'message' => 'GHN chưa được cấu hình.'];
        }
        try {
            return $this->sanitizeResponse($this->client()->get($uri, $query)->json(), $uri);
        } catch (ConnectionException $exception) {
            Log::channel('ghn')->warning('GHN connection failed', ['endpoint' => $uri, 'exception' => $exception::class]);

            return ['code' => 503, 'message' => 'Không kết nối được GHN.'];
        }
    }

    private function post(string $uri, array $data): array
    {
        if (! $this->configured()) {
            return ['code' => 503, 'message' => 'GHN chưa được cấu hình.'];
        }
        try {
            return $this->sanitizeResponse($this->client()->post($uri, $data)->json(), $uri);
        } catch (ConnectionException $exception) {
            Log::channel('ghn')->warning('GHN connection failed', ['endpoint' => $uri, 'exception' => $exception::class]);

            return ['code' => 503, 'message' => 'Không kết nối được GHN.'];
        }
    }

    /**
     * Keep upstream diagnostics in the log while only returning Vietnamese,
     * customer-safe messages to the storefront and administration screens.
     */
    private function sanitizeResponse(mixed $response, string $endpoint): array
    {
        if (! is_array($response)) {
            return ['code' => 502, 'message' => 'GHN không phản hồi dữ liệu.'];
        }

        $code = (int) ($response['code'] ?? 502);
        if ($code === 200) {
            return $response;
        }

        Log::channel('ghn')->warning('GHN returned an error response', [
            'endpoint' => $endpoint,
            'code' => $code,
        ]);

        $response['message'] = match ($code) {
            400, 422 => 'Dữ liệu giao hàng chưa hợp lệ. Vui lòng kiểm tra lại địa chỉ nhận hàng.',
            401, 403 => 'Không thể xác thực kết nối GHN. Vui lòng liên hệ cửa hàng để được hỗ trợ.',
            429 => 'GHN đang nhận quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.',
            default => 'Không thể kết nối và xử lý thông tin giao hàng từ GHN. Vui lòng thử lại sau.',
        };

        return $response;
    }
}
