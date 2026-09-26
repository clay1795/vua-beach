<?php

namespace Tests\Feature;

use App\Mail\OrderStatusUpdatedMail;
use App\Mail\ReturnRequestUpdatedMail;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseReceipt;
use App\Models\ReturnRequest;
use App\Models\SiteSetting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminAndSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_checkout_and_admin(): void
    {
        $this->get(route('checkout.create'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_order_csv_export_is_utf8_and_neutralizes_spreadsheet_formulas(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Order::create([
            'order_code' => 'VB-CSVSAFE',
            'customer_name' => '=HYPERLINK("https://evil.example")',
            'email' => '+cmd@example.test',
            'phone' => '@SUM(1+1)',
            'address' => 'Hà Nội',
            'total_amount' => 300000,
            'payment_method' => 'cod',
        ]);
        Order::create([
            'order_code' => 'VB-NOT-IN-EXPORT',
            'customer_name' => 'Khách khác',
            'email' => 'other@example.test',
            'phone' => '0900000001',
            'address' => 'Đà Nẵng',
            'total_amount' => 100000,
            'payment_method' => 'cod',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.export', ['q' => 'CSVSAFE']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+cmd@example.test", $csv);
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
        $this->assertStringNotContainsString('VB-NOT-IN-EXPORT', $csv);
    }

    public function test_order_filters_reject_invalid_dates_instead_of_crashing(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->from(route('admin.orders.index'))
            ->get(route('admin.orders.index', ['from' => 'not-a-date']))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHasErrors('from');
    }

    public function test_product_search_and_category_filter_return_the_expected_product(): void
    {
        $bikini = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $accessories = Category::create(['name' => 'Phụ kiện', 'slug' => 'phu-kien']);
        Product::create(['category_id' => $bikini->id, 'name' => 'Bikini biển xanh', 'slug' => 'bikini-bien-xanh', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        Product::create(['category_id' => $accessories->id, 'name' => 'Kính bơi', 'slug' => 'kinh-boi', 'price' => 100000, 'description' => 'Mô tả', 'status' => 'active']);

        $this->get(route('products.index', ['q' => 'Bikini', 'category' => 'bikini']))
            ->assertOk()
            ->assertSee('Bikini biển xanh')
            ->assertDontSee('Kính bơi');
    }

    public function test_admin_can_create_category_and_product_with_variants(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Đồ bơi trẻ em',
            'description' => 'Dành cho bé',
            'category_image' => UploadedFile::fake()->image('category.jpg', 1200, 800),
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::firstWhere('slug', 'do-boi-tre-em');
        $this->assertNotNull($category);
        $this->assertStringEndsWith('.webp', $category->image_url);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $category->image_url));
        $this->assertDatabaseHas('activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'category.created',
            'subject_id' => $category->id,
        ]);

        $this->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Áo bơi bé cá',
            'price' => 250000,
            'description' => 'Chất liệu mềm mại',
            'status' => 'active',
            'is_featured' => '1',
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 1000),
            'gallery_images' => [
                UploadedFile::fake()->image('gallery-1.jpg', 800, 1000),
                UploadedFile::fake()->image('gallery-2.jpg', 800, 1000),
            ],
            'variants' => [
                ['color' => 'Xanh', 'size' => 'S', 'stock' => 6],
                ['color' => 'Hồng', 'size' => 'M', 'stock' => 4],
            ],
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::firstWhere('slug', 'ao-boi-be-ca');
        $this->assertNotNull($product);
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertDatabaseCount('product_images', 2);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'color' => 'Hồng', 'size' => 'M', 'stock' => 4]);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'S', 'sku' => "VB-{$product->id}-XANH-S"]);
        $this->assertDatabaseHas('inventory_movements', ['type' => 'in', 'quantity' => 6, 'reason' => 'initial_stock']);
        $this->assertDatabaseHas('activity_logs', [
            'admin_user_id' => $admin->id,
            'action' => 'product.created',
            'subject_id' => $product->id,
        ]);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $product->image_url));
        $this->assertStringEndsWith('.webp', $product->image_url);
        $this->assertTrue($product->images->every(fn ($image) => str_ends_with($image->path, '.webp')));
    }

    public function test_uploaded_product_image_is_resized_and_encoded_as_webp(): void
    {
        Storage::fake('public');
        $url = app(ProductImageService::class)->store(UploadedFile::fake()->image('large.jpg', 2400, 1200));
        $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        [$width, $height, $type] = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame(1800, $width);
        $this->assertSame(900, $height);
        $this->assertSame(IMAGETYPE_WEBP, $type);
    }

    public function test_uploaded_logo_can_use_a_smaller_dimension_limit(): void
    {
        Storage::fake('public');
        $url = app(ProductImageService::class)->store(
            UploadedFile::fake()->image('logo.png', 2400, 800),
            'site',
            'logo',
            600,
        );
        $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));

        [$width, $height, $type] = getimagesize(Storage::disk('public')->path($path));
        $this->assertSame(600, $width);
        $this->assertSame(200, $height);
        $this->assertSame(IMAGETYPE_WEBP, $type);
    }

    public function test_product_upload_rejects_a_php_payload_disguised_as_an_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi']);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Ảnh không hợp lệ',
            'price' => 250000,
            'description' => 'Mô tả sản phẩm.',
            'status' => 'active',
            'cover_image' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["cmd"]);'),
        ])->assertSessionHasErrors('cover_image');

        $this->assertDatabaseMissing('products', ['name' => 'Ảnh không hợp lệ']);
    }

    public function test_image_pipeline_strips_payload_appended_to_a_real_image(): void
    {
        Storage::fake('public');
        $realImage = UploadedFile::fake()->image('cover.jpg', 800, 1000);
        $polyglot = UploadedFile::fake()->createWithContent(
            'polyglot.jpg',
            file_get_contents($realImage->getRealPath()).'<?php system($_GET["cmd"]);',
        );

        $url = app(ProductImageService::class)->store($polyglot);
        $path = ltrim((string) preg_replace('#^/storage/#', '', (string) parse_url($url, PHP_URL_PATH)), '/');
        $stored = Storage::disk('public')->get($path);

        $this->assertStringEndsWith('.webp', $path);
        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertSame(IMAGETYPE_WEBP, getimagesize(Storage::disk('public')->path($path))[2]);
    }

    public function test_image_cleanup_cannot_delete_outside_owned_asset_namespaces(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('documents/keep.webp', 'keep');
        Storage::disk('public')->put('products/safe.webp', 'safe');

        $service = app(ProductImageService::class);
        $service->deleteLocal('/storage/documents/keep.webp');
        $service->deleteLocal('/storage/products/../../documents/keep.webp');
        $service->deleteLocal('/storage/products/safe.webp');

        Storage::disk('public')->assertExists('documents/keep.webp');
        Storage::disk('public')->assertMissing('products/safe.webp');
    }

    public function test_image_pipeline_rejects_decompression_bomb_dimensions_before_decode(): void
    {
        Storage::fake('public');
        $ihdr = pack('NNCCCCC', 6000, 3000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
        $bomb = UploadedFile::fake()->createWithContent('bomb.png', $png);

        try {
            app(ProductImageService::class)->store($bomb);
            $this->fail('Ảnh vượt giới hạn pixel phải bị từ chối.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }

        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_product_transaction_rollback_removes_newly_processed_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Đồ bơi rollback', 'slug' => 'do-boi-rollback']);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Sản phẩm phải rollback',
            'price' => 250000,
            'description' => 'Kiểm tra transaction và filesystem.',
            'status' => 'active',
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 1000),
            'variants' => [
                ['color' => 'Xanh', 'size' => 'M', 'stock' => 2],
                ['color' => 'xanh', 'size' => 'm', 'stock' => 3],
            ],
        ])->assertSessionHasErrors('variants');

        $this->assertDatabaseMissing('products', ['name' => 'Sản phẩm phải rollback']);
        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_admin_cannot_create_duplicate_color_and_size_for_one_product(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Bikini trùng biến thể',
            'price' => 250000,
            'description' => 'Mô tả sản phẩm.',
            'status' => 'active',
            'variants' => [
                ['color' => 'Xanh', 'size' => 'M', 'stock' => 5],
                ['color' => 'Xanh', 'size' => 'M', 'stock' => 2],
            ],
        ])->assertSessionHasErrors('variants');

        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_removing_a_variant_from_product_form_hides_it_without_losing_inventory_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini hai màu', 'slug' => 'bikini-hai-mau', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $keep = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 2]);
        $removed = ProductVariant::create(['product_id' => $product->id, 'color' => 'Hồng', 'size' => 'S', 'stock' => 3]);
        InventoryMovement::create(['product_variant_id' => $removed->id, 'type' => 'in', 'quantity' => 3, 'balance_after' => 3, 'reason' => 'initial_stock', 'note' => 'Nhập kho']);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'price' => $product->price,
            'description' => $product->description,
            'status' => 'active',
            'variants' => [['id' => $keep->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 2, 'low_stock_threshold' => 5]],
        ])->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('product_variants', ['id' => $removed->id, 'is_active' => false]);
        $this->assertDatabaseHas('product_variants', ['id' => $keep->id, 'is_active' => true]);
    }

    public function test_admin_can_change_an_order_status(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'order_code' => 'SW-TEST01',
            'customer_name' => 'Khách test',
            'email' => 'khach@example.test',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'total_amount' => 200000,
            'payment_method' => 'cod',
            'status' => 'confirmed',
        ]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'shipping'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipping', 'shipping_status' => 'shipping']);
        $this->assertDatabaseHas('activity_logs', ['admin_user_id' => $admin->id, 'action' => 'order.status_updated', 'subject_id' => $order->id]);
        Mail::assertQueued(OrderStatusUpdatedMail::class, fn (OrderStatusUpdatedMail $mail) => $mail->hasTo('khach@example.test'));
    }

    public function test_confirming_a_new_order_creates_one_ghn_shipment_and_saves_its_code(): void
    {
        Mail::fake();
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
                'data' => ['order_code' => 'GHNTEST123'],
            ]),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini GHN', 'slug' => 'bikini-ghn', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 5]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-GHNTEST', 'customer_name' => 'Khách GHN', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'to_district_id' => 3440, 'to_ward_code' => '13010', 'subtotal_amount' => 300000, 'shipping_fee' => 30000, 'total_amount' => 330000, 'payment_method' => 'cod', 'status' => 'pending']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => $variant->color, 'size' => $variant->size, 'price' => 300000, 'quantity' => 1, 'subtotal' => 300000]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'confirmed'])->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'ghn_order_code' => 'GHNTEST123']);
        Http::assertSent(function ($request) use ($order) {
            return str_ends_with($request->url(), '/v2/shipping-order/create')
                && $request['client_order_code'] === $order->order_code
                && $request['to_district_id'] === 3440
                && $request['to_ward_code'] === '13010'
                && $request['required_note'] === 'CHOXEMHANGKHONGTHU';
        });
    }

    public function test_admin_can_confirm_order_when_ghn_is_not_configured(): void
    {
        Mail::fake();
        config([
            'services.ghn.token' => null,
            'services.ghn.shop_id' => null,
            'services.ghn.from_district_id' => null,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'order_code' => 'VB-NOGHN',
            'customer_name' => 'Khách không GHN',
            'email' => 'khach@example.test',
            'phone' => '0900000000',
            'address' => 'Đà Nẵng',
            'total_amount' => 200000,
            'payment_method' => 'cod',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.update', $order), ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
            'ghn_order_code' => null,
        ]);
    }

    public function test_admin_can_sync_a_ghn_shipping_status(): void
    {
        Mail::fake();
        config([
            'services.ghn.token' => 'test-ghn-token',
            'services.ghn.shop_id' => 216771,
            'services.ghn.from_district_id' => 3440,
            'services.ghn.base_url' => 'https://dev-online-gateway.ghn.vn/shiip/public-api',
        ]);
        Http::fake([
            'https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/detail' => Http::response([
                'code' => 200,
                'message' => 'Success',
                'data' => [['status' => 'delivered']],
            ]),
        ]);

        $admin = User::factory()->create(['is_admin' => true]);
        $order = Order::create([
            'order_code' => 'VB-SYNCGHN',
            'customer_name' => 'Khách GHN',
            'email' => 'khach@example.test',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'total_amount' => 200000,
            'payment_method' => 'cod',
            'status' => 'confirmed',
            'ghn_order_code' => 'GHN-SYNC-001',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.sync-ghn', $order))
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_status' => 'delivered',
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'completed',
            'source' => 'ghn',
        ]);
        Mail::assertQueued(OrderStatusUpdatedMail::class, fn (OrderStatusUpdatedMail $mail) => $mail->hasTo($order->email));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/shipping-order/detail') && $request['order_code'] === 'GHN-SYNC-001');
    }

    public function test_scheduled_ghn_sync_updates_order_lifecycle_without_regressing_completed_orders(): void
    {
        Mail::fake();
        config([
            'services.ghn.token' => 'test-ghn-token',
            'services.ghn.shop_id' => 216771,
            'services.ghn.from_district_id' => 3440,
            'services.ghn.base_url' => 'https://dev-online-gateway.ghn.vn/shiip/public-api',
        ]);
        Http::fake([
            '*/v2/shipping-order/detail' => Http::sequence()
                ->push(['code' => 200, 'data' => ['status' => 'delivering']])
                ->push(['code' => 200, 'data' => ['status' => 'delivered']]),
        ]);

        $order = Order::create([
            'order_code' => 'VB-GHN-COMMAND',
            'customer_name' => 'Khách GHN',
            'email' => 'khach@example.test',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'total_amount' => 200000,
            'payment_method' => 'cod',
            'status' => 'confirmed',
            'ghn_order_code' => 'GHN-COMMAND-001',
        ]);

        $this->artisan('ghn:sync-orders')->assertSuccessful();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipping', 'shipping_status' => 'shipping']);

        $this->artisan('ghn:sync-orders')->assertSuccessful();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed', 'shipping_status' => 'delivered']);
        $this->assertDatabaseCount('order_status_histories', 2);
        Mail::assertQueued(OrderStatusUpdatedMail::class, 2);
    }

    public function test_admin_can_change_customer_role_but_cannot_remove_the_last_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $customer), ['role' => 'admin'])
            ->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_admin' => true]);

        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $this->patch(route('admin.users.role', $otherAdmin), ['role' => 'customer'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'is_admin' => false]);
    }

    public function test_admin_can_open_inventory_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.inventory.index'))->assertOk()->assertSee('Lịch sử tồn kho');
    }

    public function test_product_with_sales_history_is_inactivated_instead_of_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini-history']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini giữ lịch sử', 'slug' => 'bikini-giu-lich-su', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        OrderItem::create(['order_id' => Order::create(['order_code' => 'VB-KEEPDATA', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 300000, 'payment_method' => 'cod', 'status' => 'pending'])->id, 'product_id' => $product->id, 'product_name' => $product->name, 'price' => 300000, 'quantity' => 1, 'subtotal' => 300000]);

        $this->actingAs($admin)->delete(route('admin.products.destroy', $product))->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'inactive']);
    }

    public function test_admin_can_update_site_branding_and_customer_cannot(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)->get(route('admin.settings.edit'))->assertForbidden();

        $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'site_name' => 'Vua Beach',
            'tagline' => 'Tự tin cùng biển xanh',
            'support_email' => 'hotro@vuabeach.test',
            'support_phone' => '0900000000',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 120),
        ])->assertRedirect();

        $this->assertDatabaseHas('site_settings', [
            'site_name' => 'Vua Beach',
            'tagline' => 'Tự tin cùng biển xanh',
        ]);
        $logoPath = SiteSetting::firstOrFail()->logo_path;
        Storage::disk('public')->assertExists($logoPath);
        $this->assertStringEndsWith('.webp', $logoPath);
        $this->assertSame(IMAGETYPE_WEBP, getimagesize(Storage::disk('public')->path($logoPath))[2]);
    }

    public function test_site_logo_rejects_a_script_disguised_as_an_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'site_name' => 'Vua Beach',
            'logo' => UploadedFile::fake()->createWithContent('logo.png', '<?php echo "unsafe";'),
        ])->assertSessionHasErrors('logo');

        Storage::disk('public')->assertDirectoryEmpty('/');
    }

    public function test_cart_does_not_accept_quantity_above_stock(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini test', 'slug' => 'bikini-test', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Đen', 'size' => 'M', 'stock' => 2]);

        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 3])
            ->assertRedirect()
            ->assertSessionHasErrors('variant_id');
        $this->assertNull(session('cart.'.$variant->id));
    }

    public function test_cancelling_order_restores_stock_once_and_terminal_order_cannot_be_reopened(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Đồ bơi test', 'slug' => 'do-boi-test', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 3]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'SW-STOCK1', 'customer_name' => 'Khách', 'email' => 'khach@example.com', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 200000, 'payment_method' => 'cod', 'status' => 'pending']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => $variant->color, 'size' => $variant->size, 'price' => 200000, 'quantity' => 2, 'subtotal' => 400000]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(5, $variant->fresh()->stock);

        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(5, $variant->fresh()->stock);

        $this->patch(route('admin.orders.update', $order), ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_admin_cannot_cancel_an_online_order_while_gateway_result_is_pending(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Thanh toán online', 'slug' => 'thanh-toan-online']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini online', 'slug' => 'bikini-online', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 3]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-ONLINE-PENDING', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'subtotal_amount' => 200000, 'shipping_fee' => 30000, 'total_amount' => 230000, 'payment_method' => 'vnpay', 'payment_status' => 'pending', 'status' => 'pending']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => $variant->color, 'size' => $variant->size, 'price' => 200000, 'quantity' => 2, 'subtotal' => 400000]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSee('<option value="confirmed"', false)
            ->assertDontSee('<option value="cancelled"', false);
        $this->patch(route('admin.orders.update', $order), ['status' => 'confirmed'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending', 'payment_status' => 'pending']);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_paid_online_cancellation_requires_explicit_once_only_refund_confirmation(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Hoàn tiền online', 'slug' => 'hoan-tien-online']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini hoàn tiền', 'slug' => 'bikini-hoan-tien', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Đen', 'size' => 'L', 'stock' => 3]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-ONLINE-PAID', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'subtotal_amount' => 200000, 'shipping_fee' => 30000, 'total_amount' => 230000, 'payment_method' => 'vnpay', 'payment_status' => 'paid', 'status' => 'confirmed', 'shipping_status' => 'ready_to_pick']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => $variant->color, 'size' => $variant->size, 'price' => 200000, 'quantity' => 2, 'subtotal' => 400000]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Đã hủy đơn và hoàn kho. Hãy hoàn tiền thực tế rồi xác nhận trên hệ thống.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'payment_status' => 'refund_pending']);
        $this->assertSame(5, $variant->fresh()->stock);
        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Đơn đã hủy nhưng chưa xác nhận hoàn tiền.')
            ->assertSee('Xác nhận đã hoàn tiền');

        $this->post(route('admin.orders.confirm-refund', $order))
            ->assertRedirect()
            ->assertSessionHas('success', 'Đã xác nhận hoàn tiền cho khách hàng.');
        $this->post(route('admin.orders.confirm-refund', $order))
            ->assertRedirect()
            ->assertSessionHas('success', 'Đơn hàng đã được xác nhận hoàn tiền trước đó.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'refunded', 'refunded_amount' => 230000]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('activity_logs', 2);
        $this->assertDatabaseHas('activity_logs', ['action' => 'order.refund_confirmed', 'subject_id' => $order->id]);
        Mail::assertQueued(OrderStatusUpdatedMail::class, 2);
    }

    public function test_sale_price_must_be_lower_than_regular_price(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Bikini sai giá',
            'price' => 200000,
            'sale_price' => 250000,
            'description' => 'Mô tả',
            'status' => 'active',
        ])->assertSessionHasErrors('sale_price');
    }

    public function test_customer_can_request_a_return_only_for_a_recent_completed_order(): void
    {
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi-doi-tra']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini đổi trả', 'slug' => 'bikini-doi-tra', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 2]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-RETURN1', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 200000, 'payment_method' => 'cod', 'status' => 'completed', 'completed_at' => now()->subDay()]);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Xanh', 'size' => 'M', 'price' => 200000, 'quantity' => 1, 'subtotal' => 200000]);

        $this->actingAs($customer)->get(route('returns.create', $order))->assertOk()->assertSee('Yêu cầu đổi trả')->assertSee('Bikini đổi trả');
        $this->actingAs($customer)->post(route('returns.store', $order), ['type' => 'refund', 'reason' => 'Không đúng mô tả'])
            ->assertSessionHasErrors(['items' => 'Vui lòng chọn ít nhất một sản phẩm cần đổi/trả.']);
        $response = $this->actingAs($customer)->post(route('returns.store', $order), ['type' => 'refund', 'reason' => 'Không đúng mô tả', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]]]);
        $response->assertRedirect(route('orders.show', $order));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('return_requests', ['order_id' => $order->id, 'type' => 'refund', 'status' => 'requested']);
        $this->assertDatabaseHas('return_request_histories', ['status' => 'requested', 'source' => 'customer']);

        $expired = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-RETURN2', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 200000, 'payment_method' => 'cod', 'status' => 'completed', 'completed_at' => now()->subDays(8)]);
        $this->actingAs($customer)->get(route('returns.create', $expired))->assertRedirect();
    }

    public function test_customer_cannot_open_another_return_form_while_one_is_active(): void
    {
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'order_code' => 'VB-RETURN-ACTIVE',
            'customer_name' => 'Khách',
            'email' => 'khach@example.test',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'total_amount' => 200000,
            'payment_method' => 'cod',
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);
        ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'type' => 'refund',
            'status' => 'requested',
            'reason' => 'Sản phẩm bị lỗi',
        ]);

        $this->actingAs($customer)
            ->get(route('returns.create', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors(['return' => 'Đơn hàng này đã có yêu cầu đổi trả đang được xử lý.']);
    }

    public function test_customer_cannot_return_the_same_purchased_quantity_twice(): void
    {
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi chống hoàn trùng', 'slug' => 'do-boi-chong-hoan-trung']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini chống hoàn trùng', 'slug' => 'bikini-chong-hoan-trung', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 1]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-RETURN-ONCE', 'customer_name' => 'Khách', 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'subtotal_amount' => 200000, 'total_amount' => 200000, 'payment_method' => 'cod', 'payment_status' => 'paid', 'status' => 'completed', 'completed_at' => now()->subDay()]);
        $orderItem = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Xanh', 'size' => 'M', 'price' => 200000, 'quantity' => 1, 'subtotal' => 200000]);
        $previous = ReturnRequest::create(['order_id' => $order->id, 'user_id' => $customer->id, 'type' => 'refund', 'status' => 'completed', 'reason' => 'Không vừa size', 'refund_amount' => 200000, 'refund_processed_at' => now(), 'completed_at' => now()]);
        $previous->items()->create(['order_item_id' => $orderItem->id, 'quantity' => 1]);

        $this->actingAs($customer)->get(route('returns.create', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors(['return' => 'Toàn bộ sản phẩm trong đơn đã được gửi yêu cầu đổi/trả trước đó.']);

        $this->actingAs($customer)->post(route('returns.store', $order), [
            'type' => 'refund',
            'reason' => 'Không đúng mô tả',
            'items' => [['order_item_id' => $orderItem->id, 'quantity' => 1]],
        ])->assertSessionHasErrors(['return' => 'Số lượng đổi/trả vượt quá số lượng còn lại của sản phẩm.']);

        $this->assertSame(1, $order->returnRequests()->count());
    }

    public function test_full_refund_uses_net_merchandise_amount_after_discount(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi hoàn giảm giá', 'slug' => 'do-boi-hoan-giam-gia']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini hoàn giảm giá', 'slug' => 'bikini-hoan-giam-gia', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 0]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-DISCOUNT-REFUND', 'customer_name' => 'Khách', 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'subtotal_amount' => 400000, 'discount_amount' => 100000, 'total_amount' => 330000, 'shipping_fee' => 30000, 'payment_method' => 'vnpay', 'payment_status' => 'paid', 'status' => 'completed', 'completed_at' => now()->subDay()]);
        $orderItem = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Xanh', 'size' => 'M', 'price' => 200000, 'quantity' => 2, 'subtotal' => 400000]);

        $this->actingAs($customer)->post(route('returns.store', $order), [
            'type' => 'refund',
            'reason' => 'Không đúng mô tả',
            'items' => [['order_item_id' => $orderItem->id, 'quantity' => 2]],
        ])->assertRedirect(route('orders.show', $order));

        $return = ReturnRequest::firstOrFail();
        $this->assertSame(300000, (int) $return->refund_amount);

        $this->actingAs($admin)->patch(route('admin.returns.update', $return), ['action' => 'approve'])->assertRedirect();
        $this->patch(route('admin.returns.update', $return), ['action' => 'receive'])->assertRedirect();
        $this->patch(route('admin.returns.update', $return), ['action' => 'complete'])->assertRedirect();

        $this->assertSame(300000, (int) $order->fresh()->refunded_amount);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(2, $variant->fresh()->stock);
    }

    public function test_refund_completion_rejects_an_amount_above_remaining_paid_merchandise(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-REFUND-LIMIT', 'customer_name' => 'Khách', 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'subtotal_amount' => 400000, 'discount_amount' => 100000, 'total_amount' => 330000, 'shipping_fee' => 30000, 'payment_method' => 'vnpay', 'payment_status' => 'paid', 'status' => 'completed', 'completed_at' => now()->subDay()]);
        $return = ReturnRequest::create(['order_id' => $order->id, 'user_id' => $customer->id, 'type' => 'refund', 'status' => 'received', 'reason' => 'Khác', 'refund_amount' => 300001, 'received_at' => now()]);

        $this->actingAs($admin)->patch(route('admin.returns.update', $return), ['action' => 'complete'])
            ->assertSessionHasErrors(['action' => 'Số tiền hoàn vượt quá số tiền sản phẩm khách đã thanh toán còn lại.']);

        $this->assertSame('received', $return->fresh()->status);
        $this->assertNull($return->fresh()->refund_processed_at);
        $this->assertSame(0, (int) $order->fresh()->refunded_amount);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_replacing_and_deleting_product_images_removes_local_files(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi-file']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Bikini ảnh',
            'slug' => 'bikini-anh',
            'price' => 200000,
            'description' => 'Mô tả',
            'image_url' => '/storage/products/old-cover.jpg',
            'status' => 'active',
        ]);
        Storage::disk('public')->put('products/old-cover.jpg', 'old');
        Storage::disk('public')->put('products/gallery.jpg', 'gallery');
        $product->images()->create(['path' => '/storage/products/gallery.jpg', 'sort_order' => 1]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => $product->name,
            'price' => $product->price,
            'description' => $product->description,
            'status' => 'active',
            'cover_image' => UploadedFile::fake()->image('new-cover.jpg', 800, 1000),
        ])->assertRedirect(route('admin.products.index'));

        Storage::disk('public')->assertMissing('products/old-cover.jpg');
        $newCover = str_replace('/storage/', '', parse_url($product->fresh()->image_url, PHP_URL_PATH));
        Storage::disk('public')->assertExists($newCover);

        $this->delete(route('admin.products.destroy', $product))->assertRedirect();
        Storage::disk('public')->assertMissing($newCover);
        Storage::disk('public')->assertMissing('products/gallery.jpg');
    }

    public function test_public_responses_have_security_headers_and_dynamic_robots_rules(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /thanh-toan')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_approved_exchange_reserves_replacement_stock_and_restores_returned_stock_when_received(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi-doi-size']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini đổi size', 'slug' => 'bikini-doi-size', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $returned = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'S', 'stock' => 2]);
        $replacement = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 3]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-EXCHANGE1', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 200000, 'payment_method' => 'cod', 'status' => 'completed', 'completed_at' => now()->subDay()]);
        $orderItem = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $returned->id, 'product_name' => $product->name, 'color' => 'Xanh', 'size' => 'S', 'price' => 200000, 'quantity' => 1, 'subtotal' => 200000]);
        $return = ReturnRequest::create(['order_id' => $order->id, 'user_id' => $customer->id, 'type' => 'exchange', 'status' => 'requested', 'reason' => 'Không vừa size']);
        $returnItem = $return->items()->create(['order_item_id' => $orderItem->id, 'quantity' => 1, 'desired_size' => 'M']);

        $this->actingAs($admin)->patch(route('admin.returns.update', $return), ['action' => 'approve', 'replacement_variant_id' => [$returnItem->id => $replacement->id]])->assertRedirect();
        $this->assertDatabaseHas('return_requests', ['id' => $return->id, 'status' => 'approved']);
        $this->assertSame(2, $replacement->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', ['product_variant_id' => $replacement->id, 'reason' => 'exchange_replacement_reserved']);

        $this->patch(route('admin.returns.update', $return), ['action' => 'receive'])->assertRedirect();
        $this->assertDatabaseHas('return_requests', ['id' => $return->id, 'status' => 'received']);
        $this->assertSame(3, $returned->fresh()->stock);
        $this->assertDatabaseHas('inventory_movements', ['product_variant_id' => $returned->id, 'reason' => 'return_received']);

        $this->patch(route('admin.returns.update', $return), ['action' => 'complete'])->assertRedirect();
        $this->assertDatabaseHas('return_requests', ['id' => $return->id, 'status' => 'completed']);
    }

    public function test_admin_can_reject_a_return_request_without_changing_stock(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-REJECT1', 'customer_name' => 'Khách', 'email' => 'khach@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 200000, 'payment_method' => 'cod', 'status' => 'completed', 'completed_at' => now()]);
        $return = ReturnRequest::create(['order_id' => $order->id, 'user_id' => $customer->id, 'type' => 'refund', 'status' => 'requested', 'reason' => 'Khác']);

        $this->actingAs($admin)->get(route('admin.returns.index'))->assertOk()->assertSee('Đổi trả &amp; hoàn hàng', false);
        $this->get(route('admin.returns.show', $return))->assertOk()->assertSee('Yêu cầu đổi trả #'.$return->id);
        $this->actingAs($admin)->patch(route('admin.returns.update', $return), ['action' => 'reject', 'admin_note' => 'Sản phẩm không đáp ứng điều kiện đổi trả.'])->assertRedirect();
        $this->assertDatabaseHas('return_requests', ['id' => $return->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('return_request_histories', ['return_request_id' => $return->id, 'status' => 'rejected', 'source' => 'admin']);
        Mail::assertQueued(ReturnRequestUpdatedMail::class, fn ($mail) => $mail->hasTo($customer->email));
    }

    public function test_partial_refund_is_recorded_exactly_once_without_marking_the_whole_order_refunded(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create();
        $category = Category::create(['name' => 'Đồ bơi hoàn tiền', 'slug' => 'do-boi-hoan-tien']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini hoàn tiền', 'slug' => 'bikini-hoan-tien', 'price' => 200000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 0]);
        $order = Order::create(['user_id' => $customer->id, 'order_code' => 'VB-PARTIAL-REFUND', 'customer_name' => 'Khách', 'email' => $customer->email, 'phone' => '0900000000', 'address' => 'Hà Nội', 'subtotal_amount' => 400000, 'total_amount' => 430000, 'payment_method' => 'cod', 'payment_status' => 'paid', 'status' => 'completed', 'completed_at' => now()]);
        $orderItem = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'color' => 'Xanh', 'size' => 'M', 'price' => 200000, 'quantity' => 2, 'subtotal' => 400000]);
        $return = ReturnRequest::create(['order_id' => $order->id, 'user_id' => $customer->id, 'type' => 'refund', 'status' => 'requested', 'reason' => 'Không vừa size', 'refund_amount' => 200000]);
        $return->items()->create(['order_item_id' => $orderItem->id, 'quantity' => 1]);

        $this->actingAs($admin)->patch(route('admin.returns.update', $return), ['action' => 'approve'])->assertRedirect();
        $this->patch(route('admin.returns.update', $return), ['action' => 'receive'])->assertRedirect();
        $this->patch(route('admin.returns.update', $return), ['action' => 'complete'])->assertRedirect();
        $this->patch(route('admin.returns.update', $return), ['action' => 'complete'])->assertRedirect();

        $this->assertSame(200000, (int) $order->fresh()->refunded_amount);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($return->fresh()->refund_processed_at);
        $this->assertSame(1, $variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_admin_can_manage_supplier_and_receive_stock_through_a_purchase_receipt(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi-nhap']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini nhập kho', 'slug' => 'bikini-nhap-kho', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 2, 'sku' => 'VB-NHAP-XANH-M']);

        $this->actingAs($admin)->post(route('admin.suppliers.store'), [
            'name' => 'Xưởng May Biển Xanh',
            'contact_name' => 'Chị Lan',
            'phone' => '0900000000',
            'is_active' => 1,
        ])->assertRedirect(route('admin.suppliers.index'));

        $supplier = Supplier::firstOrFail();
        $this->post(route('admin.purchase-receipts.store'), [
            'supplier_id' => $supplier->id,
            'received_at' => now()->format('Y-m-d H:i:s'),
            'note' => 'Nhập bổ sung mùa hè',
            'items' => [['variant_id' => $variant->id, 'quantity' => 8, 'unit_cost' => 150000]],
        ])->assertRedirect();

        $receipt = PurchaseReceipt::firstOrFail();
        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(1200000, (int) $receipt->total_cost);
        $this->assertDatabaseHas('purchase_receipt_items', ['purchase_receipt_id' => $receipt->id, 'product_variant_id' => $variant->id, 'quantity' => 8, 'unit_cost' => 150000]);
        $this->assertDatabaseHas('inventory_movements', ['purchase_receipt_id' => $receipt->id, 'product_variant_id' => $variant->id, 'type' => 'in', 'quantity' => 8, 'reason' => 'purchase_receipt', 'balance_after' => 10]);
        $this->get(route('admin.purchase-receipts.show', $receipt))->assertOk()->assertSee($receipt->receipt_code)->assertSee('Bikini nhập kho');
    }

    public function test_dashboard_shows_best_sellers_for_completed_orders_only(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi-bao-cao']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini bán chạy', 'slug' => 'bikini-ban-chay', 'price' => 300000, 'description' => 'Mô tả', 'status' => 'active']);
        $completed = Order::create(['order_code' => 'VB-REPORT1', 'customer_name' => 'Khách', 'email' => 'a@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 600000, 'payment_method' => 'cod', 'status' => 'completed']);
        OrderItem::create(['order_id' => $completed->id, 'product_id' => $product->id, 'product_name' => $product->name, 'price' => 300000, 'quantity' => 2, 'subtotal' => 600000]);
        $pending = Order::create(['order_code' => 'VB-REPORT2', 'customer_name' => 'Khách', 'email' => 'b@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 500000, 'payment_method' => 'cod', 'status' => 'pending']);
        OrderItem::create(['order_id' => $pending->id, 'product_name' => 'Không được tính', 'price' => 500000, 'quantity' => 1, 'subtotal' => 500000]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Sản phẩm bán chạy')
            ->assertSee('Bikini bán chạy')
            ->assertDontSee('Không được tính');
    }
}
