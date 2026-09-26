<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\VnpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookRequestSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversized_webhook_body_is_rejected_before_controller_processing(): void
    {
        config(['webhooks.max_payload_bytes' => 1024]);

        $response = $this->call(
            'POST',
            route('momo.ipn'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode(['padding' => str_repeat('x', 2048)]),
        );

        $response->assertStatus(413)->assertJson(['message' => 'Payload too large']);
        $this->assertDatabaseCount('webhook_receipts', 0);
    }

    public function test_malformed_webhook_fields_are_rejected_without_server_error(): void
    {
        config([
            'services.ghn.shop_id' => 123,
            'services.ghn.webhook_secret' => 'test-secret',
            'services.momo.partner_code' => 'MOMO',
        ]);

        $this->withHeader('X-GHN-Webhook-Secret', 'test-secret')
            ->postJson(route('ghn.webhook'), ['OrderCode' => ['nested'], 'ShopID' => 123])
            ->assertUnprocessable();

        $this->postJson(route('momo.ipn'), ['partnerCode' => ['nested']])
            ->assertStatus(400)
            ->assertJson(['message' => 'Invalid request']);

        $this->getJson(route('vnpay.ipn', ['vnp_TxnRef' => ['nested']]))
            ->assertOk()
            ->assertJsonPath('RspCode', '97');
    }

    public function test_signed_vnpay_callback_cannot_pay_a_non_vnpay_order(): void
    {
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TESTCODE',
            'hash_secret' => 'test-secret',
            'return_url' => 'https://example.test/return',
            'ipn_url' => 'https://example.test/ipn',
        ]);
        $order = Order::create([
            'order_code' => 'VB-COD-NOT-VNPAY',
            'customer_name' => 'Khách',
            'email' => 'customer@example.test',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'total_amount' => 150000,
            'payment_method' => 'cod',
        ]);
        $data = [
            'vnp_TmnCode' => 'TESTCODE',
            'vnp_TxnRef' => $order->order_code,
            'vnp_Amount' => 15000000,
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => '123456789',
        ];
        $data['vnp_SecureHash'] = app(VnpayService::class)->signature($data);

        $this->getJson(route('vnpay.ipn', $data))
            ->assertOk()
            ->assertJsonPath('RspCode', '97');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('webhook_receipts', 0);
    }
}
