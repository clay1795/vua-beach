<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MomoService
{
    public function __construct(
        private readonly PublicEnvironmentReadiness $readiness,
        private readonly PaymentReturnUrlResolver $returnUrls,
    ) {}

    public function configured(): bool
    {
        return $this->configurationIssues() === [];
    }

    /** @return array<int, string> */
    public function configurationIssues(): array
    {
        $issues = [];
        if (! filter_var(config('services.momo.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $issues[] = 'MOMO_ENABLED chưa đặt thành true.';
        }
        foreach (['partner_code' => 'MOMO_PARTNER_CODE', 'access_key' => 'MOMO_ACCESS_KEY', 'secret_key' => 'MOMO_SECRET_KEY'] as $key => $name) {
            if (blank(config("services.momo.$key"))) {
                $issues[] = "Thiếu $name.";
            }
        }
        if (! $this->isHttpsUrl(config('services.momo.endpoint'))) {
            $issues[] = 'MOMO_ENDPOINT phải là URL HTTPS của MoMo Sandbox.';
        }
        if (app()->environment('production') && $this->hostIs(config('services.momo.endpoint'), 'test-payment.momo.vn') && ! $this->sandboxModeIsExplicitlyEnabled()) {
            $issues[] = 'Production chỉ được dùng MOMO_ENDPOINT của Sandbox khi PAYMENT_SANDBOX_MODE=true.';
        }
        if (! $this->returnUrlIsAllowed(config('services.momo.return_url'))) {
            $issues[] = 'MOMO_RETURN_URL phải là HTTPS công khai; môi trường local chỉ cho phép http://localhost hoặc http://127.0.0.1.';
        }
        if (! $this->isPublicHttpsUrl(config('services.momo.ipn_url'))) {
            $issues[] = 'MOMO_IPN_URL phải là URL HTTPS công khai, không dùng localhost.';
        }
        if (app()->environment(['production', 'staging'])) {
            foreach (['return_url' => 'MOMO_RETURN_URL', 'ipn_url' => 'MOMO_IPN_URL'] as $key => $name) {
                if (! $this->readiness->sameOriginPublicHttpsUrl(config("services.momo.$key"), config('app.url'))) {
                    $issues[] = "$name phải cùng origin với APP_URL trên Production/Staging.";
                }
            }
        }

        return $issues;
    }

    /** @return array{request_id:string,pay_url:string,transaction_id:int,gateway_order_id:string} */
    public function createPayment(Order $order, ?PaymentTransaction $transaction = null, ?Request $request = null): array
    {
        $transaction ??= $order->paymentTransactions()->create([
            'gateway' => 'momo',
            'amount' => $order->total_amount,
            'status' => 'pending',
        ]);
        if ($transaction->order_id !== $order->id || $transaction->gateway !== 'momo' || $transaction->status !== 'pending') {
            throw new \InvalidArgumentException('Lần thanh toán MoMo không hợp lệ.');
        }

        $requestId = (string) Str::uuid();
        $gatewayOrderId = $order->order_code.'-'.$transaction->id.'-'.Str::lower(Str::random(8));
        $data = [
            'partnerCode' => (string) config('services.momo.partner_code'),
            'accessKey' => (string) config('services.momo.access_key'),
            'requestId' => $requestId,
            'amount' => (string) $order->total_amount,
            'orderId' => $gatewayOrderId,
            'orderInfo' => 'Thanh toan don hang '.$order->order_code,
            'redirectUrl' => $this->returnUrls->resolve(
                $request ?? request(),
                'momo.return',
                (string) config('services.momo.return_url'),
                (string) config('services.momo.ipn_url'),
            ),
            'ipnUrl' => (string) config('services.momo.ipn_url'),
            'extraData' => (string) $order->id,
            // MoMo's Website/App one-time wallet-payment API requires captureWallet.
            'requestType' => 'captureWallet',
            'lang' => 'vi',
        ];
        $data['signature'] = $this->requestSignature($data);

        $transaction->update([
            'gateway_order_id' => $gatewayOrderId,
            'gateway_request_id' => $requestId,
            'request_payload' => $this->safePayload($data),
        ]);
        // Keep the legacy summary columns during the transition to the 1-N ledger.
        $order->update(['momo_request_id' => $requestId, 'momo_payment_status' => 'pending']);

        try {
            // MoMo specifies a minimum 30-second API timeout for this endpoint.
            $response = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(30)
                ->post((string) config('services.momo.endpoint'), $data);
        } catch (\Throwable $exception) {
            $transaction->update([
                'status' => 'failed',
                'message' => 'Không thể kết nối MoMo.',
            ]);
            Log::channel('payment')->warning('Không thể kết nối MoMo khi tạo thanh toán.', ['order_id' => $order->id, 'exception' => $exception::class]);
            throw new \RuntimeException('Không thể kết nối MoMo. Vui lòng thử lại sau.');
        }
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ($body['resultCode'] ?? null) !== 0 || blank($body['payUrl'] ?? null)
            || ($body['orderId'] ?? null) !== $gatewayOrderId || ($body['requestId'] ?? null) !== $requestId
            || ! $this->validCreateResponseSignature($body)) {
            $transaction->update([
                'response_payload' => is_array($body) ? $this->safePayload($body) : null,
                'result_code' => is_array($body) && isset($body['resultCode']) ? (int) $body['resultCode'] : null,
                'message' => is_array($body) ? ($body['message'] ?? 'MoMo từ chối yêu cầu.') : 'MoMo phản hồi không hợp lệ.',
                'status' => 'failed',
            ]);
            Log::channel('payment')->warning('MoMo từ chối yêu cầu tạo thanh toán.', ['order_id' => $order->id, 'http_status' => $response->status(), 'result_code' => $body['resultCode'] ?? null]);
            throw new \RuntimeException('Không thể tạo giao dịch MoMo. Vui lòng chọn phương thức khác hoặc thử lại.');
        }

        $transaction->update([
            'response_payload' => $this->safePayload($body),
            'result_code' => (int) $body['resultCode'],
            'message' => $body['message'] ?? null,
            'status' => 'initiated',
        ]);

        return [
            'request_id' => $requestId,
            'pay_url' => $body['payUrl'],
            'transaction_id' => $transaction->id,
            'gateway_order_id' => $gatewayOrderId,
        ];
    }

    public function requestSignature(array $data): string
    {
        return $this->sign($this->query($data, ['accessKey', 'amount', 'extraData', 'ipnUrl', 'orderId', 'orderInfo', 'partnerCode', 'redirectUrl', 'requestId', 'requestType']));
    }

    public function validSignature(array $data): bool
    {
        $signature = (string) ($data['signature'] ?? '');
        if (blank($signature)) {
            return false;
        }
        $fields = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];
        // MoMo does not put accessKey in Return/IPN payloads. It is a merchant
        // credential and must be supplied from our server-side configuration.
        $signedData = ['accessKey' => (string) config('services.momo.access_key')] + $data;
        foreach ($fields as $field) {
            if (! array_key_exists($field, $signedData)) {
                return false;
            }
        }

        return hash_equals($this->sign($this->query($signedData, $fields)), $signature);
    }

    public function validCreateResponseSignature(array $data): bool
    {
        $signature = (string) ($data['signature'] ?? '');
        // MoMo's captureWallet response example does not include a signature. Its
        // server-to-server IPN is always signed and remains the payment authority.
        if (blank($signature)) {
            return true;
        }
        $fields = ['accessKey', 'amount', 'message', 'orderId', 'partnerCode', 'payUrl', 'requestId', 'responseTime', 'resultCode'];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                return false;
            }
        }

        return hash_equals($this->sign($this->query($data, $fields)), $signature);
    }

    private function query(array $data, array $fields): string
    {
        return collect($fields)->map(fn (string $key) => $key.'='.(string) $data[$key])->implode('&');
    }

    private function sign(string $raw): string
    {
        return hash_hmac('sha256', $raw, (string) config('services.momo.secret_key'));
    }

    private function safePayload(array $payload): array
    {
        unset($payload['signature'], $payload['accessKey']);

        return $payload;
    }

    private function isHttpsUrl(mixed $url): bool
    {
        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://');
    }

    private function isPublicHttpsUrl(mixed $url): bool
    {
        if (! $this->isHttpsUrl($url)) {
            return false;
        }

        return ! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function returnUrlIsAllowed(mixed $url): bool
    {
        if ($this->isPublicHttpsUrl($url)) {
            return true;
        }

        if (! app()->environment('local') || ! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'http'
            && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function hostIs(mixed $url, string $expectedHost): bool
    {
        return is_string($url) && strtolower((string) parse_url($url, PHP_URL_HOST)) === $expectedHost;
    }

    private function sandboxModeIsExplicitlyEnabled(): bool
    {
        return filter_var(config('services.payment_sandbox_mode'), FILTER_VALIDATE_BOOLEAN);
    }
}
