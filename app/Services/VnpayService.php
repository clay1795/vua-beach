<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class VnpayService
{
    public function __construct(
        private readonly PublicEnvironmentReadiness $readiness,
        private readonly PaymentReturnUrlResolver $returnUrls,
    ) {}

    public function configured(): bool
    {
        return $this->configurationIssues() === [];
    }

    /**
     * @return array<int, string>
     */
    public function configurationIssues(): array
    {
        $issues = [];

        if (! filter_var(config('services.vnpay.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $issues[] = 'VNPAY_ENABLED chưa đặt thành true.';
        }
        if (blank(config('services.vnpay.tmn_code'))) {
            $issues[] = 'Thiếu VNPAY_TMN_CODE (TmnCode do VNPAY cấp).';
        }
        if (blank(config('services.vnpay.hash_secret'))) {
            $issues[] = 'Thiếu VNPAY_HASH_SECRET.';
        }
        if (! $this->returnUrlIsAllowed(config('services.vnpay.return_url'))) {
            $issues[] = 'VNPAY_RETURN_URL phải là HTTPS công khai; môi trường local chỉ cho phép http://localhost hoặc http://127.0.0.1.';
        }
        if (! $this->isPublicHttpsUrl(config('services.vnpay.ipn_url'))) {
            $issues[] = 'VNPAY_IPN_URL phải là URL HTTPS công khai, không dùng localhost.';
        }
        if (app()->environment(['production', 'staging'])) {
            foreach (['return_url' => 'VNPAY_RETURN_URL', 'ipn_url' => 'VNPAY_IPN_URL'] as $key => $name) {
                if (! $this->readiness->sameOriginPublicHttpsUrl(config("services.vnpay.$key"), config('app.url'))) {
                    $issues[] = "$name phải cùng origin với APP_URL trên Production/Staging.";
                }
            }
        }
        if (! $this->isHttpsUrl(config('services.vnpay.url'))) {
            $issues[] = 'VNPAY_URL phải là URL HTTPS của sandbox hoặc production VNPAY.';
        }
        if (app()->environment('production') && $this->hostIs(config('services.vnpay.url'), 'sandbox.vnpayment.vn') && ! $this->sandboxModeIsExplicitlyEnabled()) {
            $issues[] = 'Production chỉ được dùng VNPAY_URL của Sandbox khi PAYMENT_SANDBOX_MODE=true.';
        }

        return $issues;
    }

    public function paymentUrl(Order $order, Request $request, ?PaymentTransaction $transaction = null): string
    {
        $transaction ??= $order->paymentTransactions()->create([
            'gateway' => 'vnpay',
            'amount' => $order->total_amount,
            'status' => 'pending',
        ]);
        if ($transaction->order_id !== $order->id || $transaction->gateway !== 'vnpay' || $transaction->status !== 'pending') {
            throw new \InvalidArgumentException('Lần thanh toán VNPAY không hợp lệ.');
        }

        $data = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => config('services.vnpay.tmn_code'),
            'vnp_Amount' => $order->total_amount * 100,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $order->order_code,
            'vnp_OrderInfo' => 'Thanh toan don hang '.$order->order_code,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $this->returnUrls->resolve(
                $request,
                'vnpay.return',
                (string) config('services.vnpay.return_url'),
                (string) config('services.vnpay.ipn_url'),
            ),
            'vnp_IpAddr' => $request->ip(),
            'vnp_CreateDate' => now()->format('YmdHis'),
        ];

        $transaction->update([
            'gateway_order_id' => $order->order_code,
            'request_payload' => Arr::except($data, ['vnp_IpAddr']),
            'status' => 'initiated',
        ]);

        return rtrim(config('services.vnpay.url'), '?').'?'.http_build_query($data + [
            'vnp_SecureHash' => $this->signature($data),
        ]);
    }

    public function validSignature(array $data): bool
    {
        $signature = (string) ($data['vnp_SecureHash'] ?? '');
        unset($data['vnp_SecureHash'], $data['vnp_SecureHashType']);

        return filled($signature) && hash_equals($this->signature($data), $signature);
    }

    public function signature(array $data): string
    {
        ksort($data);

        return hash_hmac('sha512', http_build_query($data), config('services.vnpay.hash_secret'));
    }

    private function isHttpsUrl(mixed $url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL)
            && str_starts_with($url, 'https://');
    }

    private function isPublicHttpsUrl(mixed $url): bool
    {
        if (! $this->isHttpsUrl($url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && ! in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
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
