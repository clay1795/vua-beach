<?php

namespace Tests\Feature;

use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\MomoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MomoPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function configureMomo(): void
    {
        config()->set('services.momo', ['enabled' => true, 'endpoint' => 'https://momo.test/create', 'partner_code' => 'MOMO', 'access_key' => 'ACCESS', 'secret_key' => 'SECRET', 'return_url' => 'https://shop.example.test/thanh-toan/momo/ket-qua', 'ipn_url' => 'https://shop.example.test/thanh-toan/momo/ipn']);
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);
    }

    private function momoCallback(Order $order, int $resultCode = 0): array
    {
        $transaction = $order->paymentTransactions()->latest('id')->first();
        $data = ['partnerCode' => 'MOMO', 'requestId' => $transaction?->gateway_request_id ?: $order->momo_request_id, 'amount' => (string) $order->total_amount, 'orderId' => $transaction?->gateway_order_id ?: $order->order_code, 'orderInfo' => 'Thanh toan don hang '.$order->order_code, 'orderType' => 'momo_wallet', 'transId' => 'TRANS-'.$order->id.'-'.($transaction?->id ?? 'legacy'), 'resultCode' => $resultCode, 'message' => $resultCode === 0 ? 'Success' : 'Failed', 'payType' => 'qr', 'responseTime' => '1720000000000', 'extraData' => (string) ($transaction?->request_payload['extraData'] ?? '')];
        $fields = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];
        $signedData = ['accessKey' => 'ACCESS'] + $data;
        $raw = collect($fields)->map(fn ($key) => $key.'='.$signedData[$key])->implode('&');
        $data['signature'] = hash_hmac('sha256', $raw, 'SECRET');

        return $data;
    }

    public function test_missing_momo_configuration_is_rejected_before_order_creation(): void
    {
        config()->set('services.momo.enabled', false);
        $this->assertFalse(app(MomoService::class)->configured());
        $this->assertContains('MOMO_ENABLED chưa đặt thành true.', app(MomoService::class)->configurationIssues());
    }

    public function test_local_environment_accepts_localhost_momo_return_but_keeps_ipn_public(): void
    {
        $this->configureMomo();
        $this->app->detectEnvironment(fn () => 'local');
        config()->set('services.momo.return_url', 'http://localhost:8000/thanh-toan/momo/ket-qua');
        config()->set('services.momo.ipn_url', 'https://tunnel.example.test/thanh-toan/momo/ipn');

        $this->assertSame([], app(MomoService::class)->configurationIssues());
    }

    public function test_production_rejects_momo_sandbox_endpoint(): void
    {
        $this->configureMomo();
        config()->set('app.url', 'https://shop.vuabeach.vn');
        config()->set('services.momo.endpoint', 'https://test-payment.momo.vn/v2/gateway/api/create');
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertContains(
            'Production chỉ được dùng MOMO_ENDPOINT của Sandbox khi PAYMENT_SANDBOX_MODE=true.',
            app(MomoService::class)->configurationIssues(),
        );
        $this->assertContains(
            'MOMO_RETURN_URL phải cùng origin với APP_URL trên Production/Staging.',
            app(MomoService::class)->configurationIssues(),
        );
    }

    public function test_production_accepts_momo_sandbox_only_after_explicit_opt_in(): void
    {
        $this->configureMomo();
        config()->set('app.url', 'https://shop.example.com');
        config()->set('services.momo.endpoint', 'https://test-payment.momo.vn/v2/gateway/api/create');
        config()->set('services.momo.return_url', 'https://shop.example.com/thanh-toan/momo/ket-qua');
        config()->set('services.momo.ipn_url', 'https://shop.example.com/thanh-toan/momo/ipn');
        config()->set('services.payment_sandbox_mode', true);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertSame([], app(MomoService::class)->configurationIssues());
    }

    public function test_create_payment_posts_officially_signed_request_and_returns_pay_url(): void
    {
        $this->configureMomo();
        $order = Order::create(['order_code' => 'VB-MOMOCREATE', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'momo']);
        Http::fake(function (ClientRequest $request) {
            $body = $request->data();
            $this->assertSame('MOMO', $body['partnerCode']);
            $this->assertSame('captureWallet', $body['requestType']);
            // This mirrors MoMo's documented captureWallet success response,
            // which intentionally does not contain a response signature.
            $reply = ['partnerCode' => 'MOMO', 'requestId' => $body['requestId'], 'amount' => $body['amount'], 'orderId' => $body['orderId'], 'message' => 'Success', 'resultCode' => 0, 'responseTime' => '1720000000000', 'payUrl' => 'https://test-payment.momo.vn/pay'];

            return Http::response($reply);
        });
        $payment = app(MomoService::class)->createPayment($order);
        $this->assertSame('https://test-payment.momo.vn/pay', $payment['pay_url']);
        $this->assertNotEmpty($payment['request_id']);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'momo',
            'status' => 'initiated',
        ]);
        $transaction = $order->paymentTransactions()->firstOrFail();
        $this->assertArrayNotHasKey('signature', $transaction->request_payload);
        $this->assertArrayNotHasKey('signature', $transaction->response_payload);
        Http::assertSentCount(1);
    }

    public function test_successful_momo_checkout_queues_order_confirmation_before_redirect(): void
    {
        Mail::fake();
        $this->configureMomo();
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);
        Http::fake(function (ClientRequest $request) {
            $body = $request->data();

            return Http::response([
                'partnerCode' => 'MOMO',
                'requestId' => $body['requestId'],
                'amount' => $body['amount'],
                'orderId' => $body['orderId'],
                'message' => 'Success',
                'resultCode' => 0,
                'responseTime' => '1720000000000',
                'payUrl' => 'https://test-payment.momo.vn/pay/checkout',
            ]);
        });
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Checkout MoMo', 'slug' => 'checkout-momo']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini Checkout MoMo', 'slug' => 'bikini-checkout-momo', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 2]);
        $checkoutToken = '019fd0ac-00c9-7961-b1d0-4724ffca8d1a';

        $this->actingAs($user)
            ->withSession(['checkout_token' => $checkoutToken, 'cart' => [$variant->id => ['quantity' => 1]]])
            ->post(route('checkout.store'), [
                'checkout_token' => $checkoutToken,
                'selected_variants' => [$variant->id],
                'customer_name' => 'Khách MoMo',
                'email' => 'momo-customer@example.test',
                'phone' => '0900000000',
                'address' => 'Hà Nội',
                'payment_method' => 'momo',
                'accept_terms' => '1',
            ])
            ->assertRedirect('https://test-payment.momo.vn/pay/checkout');

        $this->assertDatabaseHas('orders', ['payment_method' => 'momo', 'payment_status' => 'pending']);
        $this->assertDatabaseHas('payment_transactions', ['gateway' => 'momo', 'status' => 'initiated', 'amount' => 330000]);
        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('momo-customer@example.test'));
        Mail::assertQueued(OrderPlacedMail::class, 1);
    }

    public function test_invalid_signature_and_wrong_amount_are_rejected(): void
    {
        $this->configureMomo();
        $order = Order::create(['order_code' => 'VB-MOMOBAD', 'momo_request_id' => 'req-bad', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'momo']);
        $bad = $this->momoCallback($order);
        $bad['signature'] = 'bad';
        $this->postJson(route('momo.ipn'), $bad)->assertStatus(400);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid']);
        $wrong = $this->momoCallback($order);
        $wrong['amount'] = '1';
        $fields = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];
        $wrongSignedData = ['accessKey' => 'ACCESS'] + $wrong;
        $wrong['signature'] = hash_hmac('sha256', collect($fields)->map(fn ($key) => $key.'='.$wrongSignedData[$key])->implode('&'), 'SECRET');
        $this->postJson(route('momo.ipn'), $wrong)->assertStatus(400);
    }

    public function test_signed_failed_return_marks_latest_attempt_failed_without_waiting_for_ipn(): void
    {
        $this->configureMomo();
        $customer = User::factory()->create();
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-MOMO-CANCEL', 'momo_request_id' => 'req-cancel', 'customer_name' => 'Khách', 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'momo', 'payment_status' => 'pending']);
        $transaction = PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'momo', 'gateway_order_id' => $order->order_code, 'gateway_request_id' => $order->momo_request_id, 'amount' => $order->total_amount, 'status' => 'initiated']);

        $this->actingAs($customer)->get(route('momo.return', $this->momoCallback($order, 1006)))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('payment_notice', 'failed');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'failed', 'momo_payment_status' => 'failed']);
        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id, 'status' => 'failed', 'result_code' => 1006]);
    }

    public function test_ipn_accepts_numeric_transaction_id_and_response_time_from_json(): void
    {
        $this->configureMomo();
        $order = Order::create(['order_code' => 'VB-MOMO-NUMERIC', 'momo_request_id' => 'req-numeric', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'momo']);
        $data = $this->momoCallback($order);
        $data['transId'] = 987654321;
        $data['responseTime'] = 1720000000000;
        $fields = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];
        $signedData = ['accessKey' => 'ACCESS'] + $data;
        $data['signature'] = hash_hmac('sha256', collect($fields)->map(fn ($key) => $key.'='.$signedData[$key])->implode('&'), 'SECRET');

        $this->postJson(route('momo.ipn'), $data)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'momo_trans_id' => '987654321']);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'status' => 'paid', 'transaction_id' => '987654321']);
    }

    public function test_success_ipn_is_idempotent_and_failed_attempt_can_be_retried_without_a_new_order(): void
    {
        Mail::fake();
        $this->configureMomo();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'MoMo', 'slug' => 'momo']);
        $coupon = Coupon::create(['code' => 'MOMO10', 'name' => 'MoMo 10%', 'scope' => 'all', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini MoMo', 'slug' => 'bikini-momo', 'price' => 150000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Đen', 'size' => 'M', 'stock' => 0]);
        $paid = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-MOMOPAID', 'momo_request_id' => 'req-paid', 'coupon_code' => $coupon->code, 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'momo']);
        $success = $this->momoCallback($paid);
        $this->postJson(route('momo.ipn'), $success)->assertOk();
        $this->postJson(route('momo.ipn'), $success)->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $paid->id, 'payment_status' => 'paid', 'momo_trans_id' => 'TRANS-'.$paid->id.'-legacy']);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id' => $coupon->id, 'order_id' => $paid->id]);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'used_count' => 1]);
        $this->assertDatabaseHas('webhook_receipts', ['provider' => 'momo', 'status' => 'processed', 'attempts' => 1]);
        Mail::assertQueued(OrderStatusUpdatedMail::class, 1);

        $failed = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-MOMOFAIL', 'momo_request_id' => 'req-fail', 'coupon_code' => $coupon->code, 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'momo', 'payment_status' => 'pending']);
        OrderItem::create(['order_id' => $failed->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Đen', 'size' => 'M', 'price' => 150000, 'quantity' => 1, 'subtotal' => 150000]);
        $failure = $this->momoCallback($failed, 1006);
        $this->postJson(route('momo.ipn'), $failure)->assertOk();
        $this->postJson(route('momo.ipn'), $failure)->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $failed->id, 'status' => 'pending', 'payment_status' => 'failed']);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 0]);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('webhook_receipts', 2);
        $this->assertDatabaseMissing('coupon_usages', ['coupon_id' => $coupon->id, 'order_id' => $failed->id]);

        Http::fake(function (ClientRequest $request) {
            $body = $request->data();

            return Http::response(['partnerCode' => 'MOMO', 'requestId' => $body['requestId'], 'amount' => $body['amount'], 'orderId' => $body['orderId'], 'message' => 'Success', 'resultCode' => 0, 'responseTime' => '1720000000000', 'payUrl' => 'https://test-payment.momo.vn/pay/retry']);
        });
        $orderCount = Order::count();
        $this->actingAs($customer)->post(route('orders.momo.retry', $failed))
            ->assertRedirect('https://test-payment.momo.vn/pay/retry');

        $this->assertSame($orderCount, Order::count());
        $this->assertDatabaseHas('orders', ['id' => $failed->id, 'status' => 'pending', 'payment_status' => 'pending']);
        $this->assertSame(2, $failed->paymentTransactions()->count());
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $failed->id, 'status' => 'failed', 'result_code' => 1006]);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $failed->id, 'status' => 'initiated']);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 0]);
    }

    public function test_successful_momo_ipn_creates_ghn_shipment_after_payment(): void
    {
        Mail::fake();
        $this->configureMomo();
        config([
            'services.ghn.token' => 'test-ghn-token',
            'services.ghn.shop_id' => 216771,
            'services.ghn.from_district_id' => 3440,
            'services.ghn.base_url' => 'https://dev-online-gateway.ghn.vn/shiip/public-api',
        ]);
        Http::fake([
            'https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => ['order_code' => 'GHN-MOMO-001'],
            ]),
        ]);

        $customer = User::factory()->create();
        $category = Category::create(['name' => 'MoMo GHN', 'slug' => 'momo-ghn']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bộ bơi MoMo GHN', 'slug' => 'bo-boi-momo-ghn', 'price' => 150000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Đen', 'size' => 'M', 'stock' => 1]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-MOMOGHN', 'momo_request_id' => 'req-momo-ghn', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'to_district_id' => 3440, 'to_ward_code' => '13010', 'subtotal_amount' => 150000, 'shipping_fee' => 30000, 'total_amount' => 180000, 'payment_method' => 'momo', 'payment_status' => 'pending']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Đen', 'size' => 'M', 'price' => 150000, 'quantity' => 1, 'subtotal' => 150000]);
        PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'momo', 'gateway_order_id' => 'VB-MOMOGHN-1-test', 'gateway_request_id' => 'req-momo-ghn', 'amount' => 180000, 'status' => 'initiated']);

        $this->postJson(route('momo.ipn'), $this->momoCallback($order))->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'confirmed', 'shipping_status' => 'ready_to_pick', 'ghn_order_code' => 'GHN-MOMO-001']);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'status' => 'paid']);
        Http::assertSentCount(1);
    }

    public function test_second_successful_momo_attempt_is_flagged_for_refund(): void
    {
        Mail::fake();
        $this->configureMomo();
        $customer = User::factory()->create();
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-MOMODOUBLE', 'momo_request_id' => 'req-first', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'momo', 'payment_status' => 'pending']);
        PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'momo', 'gateway_order_id' => 'VB-MOMODOUBLE-1-test', 'gateway_request_id' => 'req-first', 'amount' => 150000, 'status' => 'initiated']);
        $firstSuccess = $this->momoCallback($order);
        $this->postJson(route('momo.ipn'), $firstSuccess)->assertOk();

        PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'momo', 'gateway_order_id' => 'VB-MOMODOUBLE-2-test', 'gateway_request_id' => 'req-second', 'amount' => 150000, 'status' => 'initiated']);
        $secondSuccess = $this->momoCallback($order->fresh());
        $this->postJson(route('momo.ipn'), $secondSuccess)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertSame(1, $order->paymentTransactions()->where('status', 'paid')->count());
        $this->assertSame(1, $order->paymentTransactions()->where('status', 'refund_pending')->count());
        $this->assertDatabaseCount('webhook_receipts', 2);
    }

    public function test_return_page_explains_when_momo_has_cancelled_the_order(): void
    {
        $this->configureMomo();
        $customer = User::factory()->create();
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-MOMORETURN', 'momo_request_id' => 'req-return', 'customer_name' => 'Khách', 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'momo', 'status' => 'cancelled', 'shipping_status' => 'cancelled']);

        $this->actingAs($customer)->get(route('momo.return', $this->momoCallback($order, 1006)))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('payment_notice', 'cancelled');
        $this->assertDatabaseCount('payment_transactions', 0);
    }
}
