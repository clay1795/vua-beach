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
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Services\VnpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private const CHECKOUT_TOKEN = '019fd0ac-00c9-7961-b1d0-4724ffca8d1a';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['checkout_token' => self::CHECKOUT_TOKEN]);
    }

    public function test_vnpay_preflight_reports_exact_missing_configuration_and_accepts_public_https_urls(): void
    {
        config()->set('services.vnpay', [
            'enabled' => false,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => null,
            'hash_secret' => null,
            'return_url' => 'http://127.0.0.1:8000/thanh-toan/vnpay/ket-qua',
            'ipn_url' => 'http://localhost:8000/thanh-toan/vnpay/ipn',
        ]);
        $vnpay = app(VnpayService::class);
        $this->assertFalse($vnpay->configured());
        $this->assertContains('VNPAY_ENABLED chưa đặt thành true.', $vnpay->configurationIssues());
        $this->assertContains('VNPAY_RETURN_URL phải là HTTPS công khai; môi trường local chỉ cho phép http://localhost hoặc http://127.0.0.1.', $vnpay->configurationIssues());

        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TESTCODE',
            'hash_secret' => 'test-secret',
            'return_url' => 'https://shop.example.test/thanh-toan/vnpay/ket-qua',
            'ipn_url' => 'https://shop.example.test/thanh-toan/vnpay/ipn',
        ]);
        $this->assertSame([], $vnpay->configurationIssues());
        $this->assertTrue($vnpay->configured());
    }

    public function test_local_environment_accepts_localhost_vnpay_return_but_keeps_ipn_public(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TESTCODE',
            'hash_secret' => 'test-secret',
            'return_url' => 'http://localhost:8000/thanh-toan/vnpay/ket-qua',
            'ipn_url' => 'https://tunnel.example.test/thanh-toan/vnpay/ipn',
        ]);

        $this->assertSame([], app(VnpayService::class)->configurationIssues());
    }

    public function test_production_rejects_vnpay_sandbox_endpoint(): void
    {
        config()->set('app.url', 'https://shop.vuabeach.vn');
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'PROD-TMN',
            'hash_secret' => 'production-secret',
            'return_url' => 'https://shop.example.com/thanh-toan/vnpay/ket-qua',
            'ipn_url' => 'https://shop.example.com/thanh-toan/vnpay/ipn',
        ]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertContains(
            'Production chỉ được dùng VNPAY_URL của Sandbox khi PAYMENT_SANDBOX_MODE=true.',
            app(VnpayService::class)->configurationIssues(),
        );
        $this->assertContains(
            'VNPAY_RETURN_URL phải cùng origin với APP_URL trên Production/Staging.',
            app(VnpayService::class)->configurationIssues(),
        );
    }

    public function test_production_accepts_vnpay_sandbox_only_after_explicit_opt_in(): void
    {
        config()->set('app.url', 'https://shop.example.com');
        config()->set('services.payment_sandbox_mode', true);
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'SANDBOX-TMN',
            'hash_secret' => 'sandbox-secret',
            'return_url' => 'https://shop.example.com/thanh-toan/vnpay/ket-qua',
            'ipn_url' => 'https://shop.example.com/thanh-toan/vnpay/ipn',
        ]);
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertSame([], app(VnpayService::class)->configurationIssues());
    }

    public function test_storefront_displays_an_unambiguous_sandbox_warning(): void
    {
        config()->set('services.payment_sandbox_mode', true);
        config()->set('services.payment_sandbox_labels', true);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Chế độ thử nghiệm:')
            ->assertSee('Không có giao dịch hay khoản tiền thật nào được xử lý.');
    }

    public function test_sandbox_labels_can_be_hidden_without_disabling_sandbox_payments(): void
    {
        config()->set('services.payment_sandbox_mode', true);
        config()->set('services.payment_sandbox_labels', false);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini test', 'slug' => 'bikini-test', 'price' => 200000, 'description' => 'Mô tả sản phẩm', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 5]);

        $this->get(route('home'))->assertOk()->assertDontSee('payment-sandbox-banner', false);
        $this->actingAs($user)->withSession(['cart' => [$variant->id => ['quantity' => 1]]])
            ->get(route('checkout.create', ['selected_variants' => [$variant->id]]))
            ->assertOk()
            ->assertDontSee('payment-sandbox-banner', false)
            ->assertDontSee('sandbox-chip', false);
        $this->assertTrue(config('services.payment_sandbox_mode'));
    }

    public function test_customer_can_browse_a_product_and_add_it_to_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini test', 'slug' => 'bikini-test', 'price' => 200000, 'description' => 'Mô tả sản phẩm', 'is_featured' => true, 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 5]);
        ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'L', 'stock' => 2]);
        ProductVariant::create(['product_id' => $product->id, 'color' => 'Hồng', 'size' => 'S', 'stock' => 3]);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('Thêm vào giỏ')
            ->assertSee('type="image/avif"', false)
            ->assertSee('images/vua-beach-hero-v2.avif', false)
            ->assertSee('name="quantity" value="1"', false);
        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Bikini test')
            ->assertSee('colorChoices', false)
            ->assertSee('sizeChoices', false)
            ->assertSee('variantStockNote', false)
            ->assertSee('selectedVariant', false);
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect(route('cart.index'));
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Bikini test')
            ->assertSee('selected_variants[]', false)
            ->assertSee('cart-quantity-form', false)
            ->assertSee('name="quantity" min="1" max="10" value="2"', false);
        $this->get(route('checkout.create', ['selected_variants' => [$variant->id]]))
            ->assertOk()
            ->assertSee('Thanh toán trực tiếp')
            ->assertSee('Ví điện tử MoMo');
    }

    public function test_verified_customer_can_save_a_favorite_and_review_only_a_completed_purchase(): void
    {
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi nữ', 'slug' => 'do-boi-nu']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini đánh giá', 'slug' => 'bikini-danh-gia', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 3]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-REVIEW01', 'customer_name' => $customer->name, 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 300000, 'payment_method' => 'cod', 'status' => 'completed', 'completed_at' => now()]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Xanh', 'size' => 'M', 'price' => 300000, 'quantity' => 1, 'subtotal' => 300000]);

        $this->actingAs($customer)->post(route('wishlist.toggle', $product))->assertRedirect();
        $this->assertDatabaseHas('wishlists', ['user_id' => $customer->id, 'product_id' => $product->id]);

        $this->post(route('reviews.store', $product), ['order_id' => $order->id, 'rating' => 5, 'content' => 'Form mặc đẹp và vừa vặn.'])->assertRedirect();
        $this->assertDatabaseHas('product_reviews', ['user_id' => $customer->id, 'product_id' => $product->id, 'order_id' => $order->id, 'rating' => 5]);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Form mặc đẹp và vừa vặn.');

        $otherOrder = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-NOTDONE', 'customer_name' => $customer->name, 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 300000, 'payment_method' => 'cod', 'status' => 'pending']);
        OrderItem::create(['order_id' => $otherOrder->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Xanh', 'size' => 'M', 'price' => 300000, 'quantity' => 1, 'subtotal' => 300000]);
        $this->post(route('reviews.store', $product), ['order_id' => $otherOrder->id, 'rating' => 1])->assertNotFound();
        $this->assertSame(1, ProductReview::count());
    }

    public function test_customer_can_place_an_order(): void
    {
        Mail::fake();
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);

        $user = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi nữ', 'slug' => 'do-boi-nu']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Áo bơi test', 'slug' => 'ao-boi-test', 'price' => 300000, 'description' => 'Mô tả sản phẩm', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Đen', 'size' => 'L', 'stock' => 4]);

        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $payload = ['checkout_token' => self::CHECKOUT_TOKEN, 'selected_variants' => [$variant->id], 'customer_name' => 'Khách test', 'email' => 'khach@test.com', 'phone' => '0900000000', 'address' => 'Hà Nội', 'payment_method' => 'cod', 'accept_terms' => '1'];
        $firstRedirect = $this->post(route('checkout.store'), $payload)->assertRedirect()->headers->get('Location');
        $this->post(route('checkout.store'), $payload)->assertRedirect($firstRedirect);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['subtotal_amount' => 300000, 'shipping_fee' => 30000, 'total_amount' => 330000]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 3]);
        $this->assertDatabaseHas('inventory_movements', ['product_variant_id' => $variant->id, 'type' => 'out', 'quantity' => 1, 'balance_after' => 3, 'reason' => 'order_placed']);
        Mail::assertQueued(OrderPlacedMail::class, fn (OrderPlacedMail $mail) => $mail->hasTo('khach@test.com'));
        Mail::assertQueued(OrderPlacedMail::class, 1);
    }

    public function test_cod_checkout_creates_payment_ledger_and_ghn_shipment_immediately(): void
    {
        Mail::fake();
        config([
            'services.ghn.token' => 'test-ghn-token',
            'services.ghn.shop_id' => 216771,
            'services.ghn.from_district_id' => 3440,
            'services.ghn.base_url' => 'https://dev-online-gateway.ghn.vn/shiip/public-api',
        ]);
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/v2/shipping-order/fee')) {
                return Http::response(['code' => 200, 'message' => 'Success', 'data' => ['total' => 31500]]);
            }

            if (str_ends_with($request->url(), '/v2/shipping-order/create')) {
                return Http::response(['code' => 200, 'message' => 'Success', 'data' => ['order_code' => 'GHN-COD-001']]);
            }

            return Http::response(['code' => 404], 404);
        });

        $user = User::factory()->create();
        $category = Category::create(['name' => 'COD GHN', 'slug' => 'cod-ghn']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bộ bơi COD', 'slug' => 'bo-boi-cod', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 3]);

        $this->actingAs($user)
            ->withSession(['checkout_token' => self::CHECKOUT_TOKEN, 'cart' => [$variant->id => ['quantity' => 1]]])
            ->post(route('checkout.store'), [
                'checkout_token' => self::CHECKOUT_TOKEN,
                'selected_variants' => [$variant->id],
                'customer_name' => 'Khách COD',
                'email' => 'cod@example.test',
                'phone' => '0900000000',
                'address' => 'Hà Nội',
                'to_district_id' => 3440,
                'to_ward_code' => '13010',
                'payment_method' => 'cod',
                'accept_terms' => '1',
            ])->assertRedirect();

        $order = Order::query()->sole();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
            'shipping_status' => 'ready_to_pick',
            'ghn_order_code' => 'GHN-COD-001',
            'shipping_fee' => 31500,
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'cod',
            'status' => 'pending',
            'amount' => 331500,
        ]);
        Http::assertSentCount(2);
        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/v2/shipping-order/create')
            && $request['client_order_code'] === $order->order_code
            && $request['cod_amount'] === 300000);
    }

    public function test_checkout_rejects_an_expired_or_foreign_submission_token(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Token', 'slug' => 'token']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Token test', 'slug' => 'token-test', 'price' => 100000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 1]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'checkout_token' => '019fd0ac-00c9-7961-b1d0-4724ffca8d1b',
            'selected_variants' => [$variant->id],
            'customer_name' => 'Khách test',
            'email' => 'khach@example.test',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'cod',
            'accept_terms' => '1',
        ])->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_momo_order_is_not_accepted_before_gateway_is_configured(): void
    {
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);
        config()->set('services.momo.enabled', false);

        $user = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini MoMo', 'slug' => 'bikini-momo', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 2]);

        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), ['checkout_token' => self::CHECKOUT_TOKEN, 'selected_variants' => [$variant->id], 'customer_name' => 'Khách test', 'email' => 'khach@test.com', 'phone' => '0900000000', 'address' => 'Hà Nội', 'payment_method' => 'momo', 'accept_terms' => '1'])
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_vnpay_order_is_not_accepted_before_gateway_is_configured(): void
    {
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);
        config()->set('services.vnpay.enabled', false);

        $user = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi VNPAY', 'slug' => 'do-boi-vnpay']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini VNPAY', 'slug' => 'bikini-vnpay', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 2]);

        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post(route('checkout.store'), ['checkout_token' => self::CHECKOUT_TOKEN, 'selected_variants' => [$variant->id], 'customer_name' => 'Khách test', 'email' => 'khach@test.com', 'phone' => '0900000000', 'address' => 'Hà Nội', 'payment_method' => 'vnpay', 'accept_terms' => '1'])
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_configured_vnpay_checkout_reserves_stock_and_redirects_to_a_signed_gateway_url(): void
    {
        Mail::fake();
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TESTCODE',
            'hash_secret' => 'test-secret',
            'return_url' => 'https://shop.example.test/thanh-toan/vnpay/ket-qua',
            'ipn_url' => 'https://shop.example.test/thanh-toan/vnpay/ipn',
        ]);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi VNPAY sandbox', 'slug' => 'do-boi-vnpay-sandbox']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini VNPAY sandbox', 'slug' => 'bikini-vnpay-sandbox', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 2]);

        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $response = $this->post(route('checkout.store'), [
            'checkout_token' => self::CHECKOUT_TOKEN,
            'selected_variants' => [$variant->id],
            'customer_name' => 'Khách test',
            'email' => $user->email,
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'payment_method' => 'vnpay',
            'accept_terms' => '1',
        ]);

        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $order = Order::firstOrFail();
        $this->assertSame($order->order_code, $params['vnp_TxnRef']);
        $this->assertSame('TESTCODE', $params['vnp_TmnCode']);
        $this->assertSame('33000000', $params['vnp_Amount']);
        $this->assertTrue(app(VnpayService::class)->validSignature($params));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_method' => 'vnpay', 'payment_status' => 'pending', 'status' => 'pending', 'total_amount' => 330000]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 1]);
        $transaction = PaymentTransaction::query()->sole();
        $this->assertSame($order->id, $transaction->order_id);
        $this->assertSame('vnpay', $transaction->gateway);
        $this->assertSame($order->order_code, $transaction->gateway_order_id);
        $this->assertSame('initiated', $transaction->status);
        $this->assertSame(330000, (int) $transaction->amount);
        $this->assertSame($params['vnp_ReturnUrl'], $transaction->request_payload['vnp_ReturnUrl']);
        $this->assertArrayNotHasKey('vnp_IpAddr', $transaction->request_payload);
        $this->assertArrayNotHasKey('vnp_SecureHash', $transaction->request_payload);
    }

    public function test_vnpay_ipn_marks_the_matching_order_as_paid(): void
    {
        Mail::fake();
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TESTCODE',
            'hash_secret' => 'test-secret',
            'return_url' => 'https://example.test/return',
            'ipn_url' => 'https://example.test/ipn',
        ]);
        $order = Order::create(['order_code' => 'VB-VNPAYTEST', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'vnpay']);
        $data = ['vnp_TmnCode' => 'TESTCODE', 'vnp_TxnRef' => $order->order_code, 'vnp_Amount' => 15000000, 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_TransactionNo' => '123456789', 'vnp_BankCode' => 'NCB'];
        $data['vnp_SecureHash'] = app(VnpayService::class)->signature($data);

        $this->get(route('vnpay.ipn', $data))->assertOk()->assertJsonPath('RspCode', '00');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'vnpay_transaction_no' => '123456789']);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'gateway' => 'vnpay', 'gateway_order_id' => $order->order_code, 'transaction_id' => '123456789', 'status' => 'paid', 'response_code' => '00', 'bank_code' => 'NCB', 'amount' => 150000]);
        $this->assertArrayNotHasKey('vnp_SecureHash', PaymentTransaction::query()->sole()->response_payload);
        $this->assertDatabaseHas('webhook_receipts', ['provider' => 'vnpay', 'status' => 'processed', 'attempts' => 1]);
        Mail::assertQueued(OrderStatusUpdatedMail::class);
    }

    public function test_failed_vnpay_ipn_releases_reserved_stock_once(): void
    {
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TESTCODE',
            'hash_secret' => 'test-secret',
            'return_url' => 'https://example.test/return',
            'ipn_url' => 'https://example.test/ipn',
        ]);
        $category = Category::create(['name' => 'Đồ bơi VNPAY lỗi', 'slug' => 'do-boi-vnpay-loi']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini hoàn tồn', 'slug' => 'bikini-hoan-ton', 'price' => 150000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Đen', 'size' => 'M', 'stock' => 1]);
        $order = Order::create(['order_code' => 'VB-VNPAYFAIL', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'vnpay']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Đen', 'size' => 'M', 'price' => 150000, 'quantity' => 1, 'subtotal' => 150000]);
        $variant->decrement('stock');
        $data = ['vnp_TmnCode' => 'TESTCODE', 'vnp_TxnRef' => $order->order_code, 'vnp_Amount' => 15000000, 'vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '01'];
        $data['vnp_SecureHash'] = app(VnpayService::class)->signature($data);

        $this->get(route('vnpay.ipn', $data))->assertOk()->assertJsonPath('RspCode', '00');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'payment_status' => 'failed']);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 1]);
        $this->assertDatabaseHas('inventory_movements', ['order_id' => $order->id, 'reason' => 'vnpay_payment_failed', 'quantity' => 1]);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'gateway' => 'vnpay', 'status' => 'failed', 'response_code' => '24', 'amount' => 150000]);

        $this->get(route('vnpay.ipn', $data))->assertOk()->assertJsonPath('RspCode', '00');
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('webhook_receipts', 1);

        $lateSuccess = $data;
        unset($lateSuccess['vnp_SecureHash']);
        $lateSuccess['vnp_ResponseCode'] = '00';
        $lateSuccess['vnp_TransactionStatus'] = '00';
        $lateSuccess['vnp_TransactionNo'] = 'LATE-SUCCESS-001';
        $lateSuccess['vnp_SecureHash'] = app(VnpayService::class)->signature($lateSuccess);

        $this->get(route('vnpay.ipn', $lateSuccess))->assertOk()->assertJsonPath('RspCode', '00');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'payment_status' => 'refund_pending']);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'gateway' => 'vnpay', 'status' => 'paid', 'transaction_id' => 'LATE-SUCCESS-001']);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 1]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('webhook_receipts', 2);
    }

    public function test_vnpay_return_never_mutates_order_or_inventory_before_ipn(): void
    {
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TESTCODE',
            'hash_secret' => 'test-secret',
            'return_url' => 'https://example.test/return',
            'ipn_url' => 'https://example.test/ipn',
        ]);
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi VNPAY trả về', 'slug' => 'do-boi-vnpay-tra-ve']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini trang trả về', 'slug' => 'bikini-trang-tra-ve', 'price' => 150000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Trắng', 'size' => 'S', 'stock' => 0]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-VNPAYRETURN', 'customer_name' => 'Khách', 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'vnpay']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Trắng', 'size' => 'S', 'price' => 150000, 'quantity' => 1, 'subtotal' => 150000]);
        $data = ['vnp_TmnCode' => 'TESTCODE', 'vnp_TxnRef' => $order->order_code, 'vnp_Amount' => 15000000, 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '01'];
        $data['vnp_SecureHash'] = app(VnpayService::class)->signature($data);

        $this->actingAs($customer)->get(route('vnpay.return', $data))->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending', 'payment_status' => 'unpaid']);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 0]);
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_vnpay_return_with_a_valid_signature_but_wrong_amount_never_marks_order_paid(): void
    {
        config()->set('services.vnpay', [
            'enabled' => true,
            'url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'tmn_code' => 'TESTCODE',
            'hash_secret' => 'test-secret',
            'return_url' => 'https://shop.example.test/thanh-toan/vnpay/ket-qua',
            'ipn_url' => 'https://shop.example.test/thanh-toan/vnpay/ipn',
        ]);
        $customer = User::factory()->create();
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-VNPAYAMOUNT', 'customer_name' => 'Khách', 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'vnpay']);
        $data = ['vnp_TmnCode' => 'TESTCODE', 'vnp_TxnRef' => $order->order_code, 'vnp_Amount' => 14900000, 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00'];
        $data['vnp_SecureHash'] = app(VnpayService::class)->signature($data);

        $this->actingAs($customer)->get(route('vnpay.return', $data))->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid', 'status' => 'pending']);
    }

    public function test_only_admin_can_open_the_management_dashboard(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Tổng quan cửa hàng');
    }

    public function test_inactive_product_cannot_be_added_to_cart(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi ẩn', 'slug' => 'do-boi-an']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Sản phẩm đã ẩn', 'slug' => 'san-pham-da-an', 'price' => 100000, 'description' => 'Mô tả', 'status' => 'inactive']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Đen', 'size' => 'M', 'stock' => 5]);

        $this->actingAs($user)
            ->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertSessionHasErrors('variant_id');

        $this->assertNull(session('cart.'.$variant->id));
    }

    public function test_shipping_api_fails_gracefully_until_ghn_is_configured(): void
    {
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('locations.provinces'))
            ->assertStatus(503)
            ->assertJsonPath('message', 'GHN chưa được cấu hình.');
    }

    public function test_cart_quantity_update_returns_live_totals_for_the_browser(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini trực quan', 'slug' => 'bikini-truc-quan', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 5]);
        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->patchJson(route('cart.update', $variant), ['quantity' => 3])
            ->assertOk()->assertJsonPath('quantity', 3)->assertJsonPath('subtotal', 600000)->assertJsonPath('cart_count', 3);
    }

    public function test_customer_can_add_edit_and_delete_an_address(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('addresses.store'), ['label' => 'Nhà riêng', 'recipient_name' => 'Khách hàng', 'phone' => '0900000000', 'address' => '12 Phố Test', 'province_name' => 'Hà Nội', 'district_name' => 'Nam Từ Liêm', 'ward_name' => 'Mỹ Đình 1'])->assertRedirect();
        $address = ShippingAddress::firstOrFail();
        $this->patch(route('addresses.update', $address), ['label' => 'Công ty', 'recipient_name' => 'Khách hàng', 'phone' => '0900000000', 'address' => '99 Phố Test', 'province_name' => 'Hà Nội', 'district_name' => 'Cầu Giấy', 'ward_name' => 'Dịch Vọng'])->assertRedirect();
        $this->assertDatabaseHas('shipping_addresses', ['id' => $address->id, 'label' => 'Công ty', 'district_name' => 'Cầu Giấy']);
        $this->delete(route('addresses.destroy', $address))->assertRedirect();
        $this->assertDatabaseMissing('shipping_addresses', ['id' => $address->id]);
    }

    public function test_coupon_is_checked_server_side_and_reduces_the_order_total(): void
    {
        Mail::fake();
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini ưu đãi', 'slug' => 'bikini-uu-dai', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 3]);
        Coupon::create(['code' => 'SUMMER10', 'name' => 'Giảm 10%', 'type' => 'percent', 'value' => 10, 'min_order_amount' => 200000, 'is_active' => true]);

        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->postJson(route('checkout.coupon'), ['selected_variants' => [$variant->id], 'coupon_code' => 'summer10'])
            ->assertOk()->assertJsonPath('discount', 30000);
        $this->post(route('checkout.store'), ['checkout_token' => self::CHECKOUT_TOKEN, 'selected_variants' => [$variant->id], 'customer_name' => 'Khách test', 'email' => 'khach@test.com', 'phone' => '0900000000', 'address' => 'Hà Nội', 'payment_method' => 'cod', 'coupon_code' => 'SUMMER10', 'accept_terms' => '1'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['coupon_code' => 'SUMMER10', 'discount_amount' => 30000, 'total_amount' => 300000]);
        $this->assertDatabaseHas('coupons', ['code' => 'SUMMER10', 'used_count' => 1]);
    }

    public function test_targeted_coupon_only_discounts_selected_products_and_respects_per_customer_limit(): void
    {
        Mail::fake();
        config()->set('services.ghn.token', null);
        config()->set('services.ghn.shop_id', null);
        config()->set('services.ghn.from_district_id', null);
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini-target']);
        $eligible = Product::create(['category_id' => $category->id, 'name' => 'Bikini được giảm', 'slug' => 'bikini-duoc-giam', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $other = Product::create(['category_id' => $category->id, 'name' => 'Kính không giảm', 'slug' => 'kinh-khong-giam', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $eligibleVariant = ProductVariant::create(['product_id' => $eligible->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 3]);
        $otherVariant = ProductVariant::create(['product_id' => $other->id, 'color' => 'Đen', 'size' => 'F', 'stock' => 3]);
        $coupon = Coupon::create(['code' => 'TARGET10', 'name' => 'Giảm bikini', 'scope' => 'products', 'type' => 'percent', 'value' => 10, 'per_user_limit' => 1, 'is_active' => true]);
        $coupon->products()->attach($eligible);

        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $eligibleVariant->id, 'quantity' => 1]);
        $this->post(route('cart.add'), ['variant_id' => $otherVariant->id, 'quantity' => 1]);
        $this->postJson(route('checkout.coupon'), ['selected_variants' => [$eligibleVariant->id, $otherVariant->id], 'coupon_code' => 'target10'])
            ->assertOk()->assertJsonPath('discount', 30000);
        $this->post(route('checkout.store'), ['checkout_token' => self::CHECKOUT_TOKEN, 'selected_variants' => [$eligibleVariant->id, $otherVariant->id], 'customer_name' => 'Khách test', 'email' => 'khach@test.com', 'phone' => '0900000000', 'address' => 'Hà Nội', 'payment_method' => 'cod', 'coupon_code' => 'TARGET10', 'accept_terms' => '1'])->assertRedirect();
        $this->assertDatabaseHas('coupon_usages', ['coupon_id' => $coupon->id, 'user_id' => $user->id]);

        $this->post(route('cart.add'), ['variant_id' => $eligibleVariant->id, 'quantity' => 1]);
        $this->postJson(route('checkout.coupon'), ['selected_variants' => [$eligibleVariant->id], 'coupon_code' => 'TARGET10'])
            ->assertStatus(422);
    }

    public function test_customer_can_cancel_a_pending_cod_order_and_stock_is_restored(): void
    {
        Mail::fake();
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini hủy đơn', 'slug' => 'bikini-huy-don', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Đen', 'size' => 'M', 'stock' => 1]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-CANCEL1', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 200000, 'payment_method' => 'cod', 'status' => 'pending']);
        $order->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Đen', 'size' => 'M', 'price' => 200000, 'quantity' => 1, 'subtotal' => 200000]);

        $this->actingAs($customer)->post(route('orders.cancel', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 2]);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'status' => 'cancelled', 'source' => 'customer']);
    }
}
