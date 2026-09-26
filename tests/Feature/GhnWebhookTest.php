<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebhookReceipt;
use App\Services\GHNOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GhnWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'services.ghn.shop_id' => 123456,
            'services.ghn.webhook_secret' => 'ghn-webhook-secret-for-tests',
        ]);
    }

    public function test_valid_delivered_webhook_advances_order_and_replay_is_a_no_op(): void
    {
        $order = $this->order();
        $payload = $this->payload('delivered');

        $this->withHeader('X-GHN-Webhook-Secret', 'ghn-webhook-secret-for-tests')
            ->postJson(route('ghn.webhook'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'OK')
            ->assertJsonPath('shipping_status', 'delivered');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('order_status_histories', 3);
        $this->assertDatabaseCount('webhook_receipts', 1);

        $this->withHeader('X-GHN-Webhook-Secret', 'ghn-webhook-secret-for-tests')
            ->postJson(route('ghn.webhook'), $payload)
            ->assertOk()
            ->assertJsonPath('message', 'OK');

        $this->assertDatabaseCount('order_status_histories', 3);
        $this->assertSame(1, WebhookReceipt::firstOrFail()->attempts);
    }

    public function test_webhook_rejects_wrong_secret_and_wrong_shop_without_mutation(): void
    {
        $order = $this->order();

        $this->withHeader('X-GHN-Webhook-Secret', 'wrong-secret')
            ->postJson(route('ghn.webhook'), $this->payload('delivered'))
            ->assertUnauthorized();

        $payload = $this->payload('delivered');
        $payload['ShopID'] = 999999;
        $this->withHeader('X-GHN-Webhook-Secret', 'ghn-webhook-secret-for-tests')
            ->postJson(route('ghn.webhook'), $payload)
            ->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('webhook_receipts', 0);
    }

    public function test_reused_event_key_with_changed_payload_is_rejected(): void
    {
        $order = $this->order();
        $payload = $this->payload('transporting');

        $this->withHeader('X-GHN-Webhook-Secret', 'ghn-webhook-secret-for-tests')
            ->postJson(route('ghn.webhook'), $payload)
            ->assertOk();

        $payload['Description'] = 'Payload was changed';
        $this->withHeader('X-GHN-Webhook-Secret', 'ghn-webhook-secret-for-tests')
            ->postJson(route('ghn.webhook'), $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Conflicting event');

        $this->assertSame('shipping', $order->fresh()->status);
        $this->assertDatabaseCount('webhook_receipts', 1);
    }

    public function test_delivery_failure_can_recover_to_delivered_through_valid_transition_path(): void
    {
        $order = $this->order(['status' => 'shipping', 'shipping_status' => 'shipping']);
        $sync = app(GHNOrderSyncService::class);

        $sync->apply($order, 'delivery_fail');
        $this->assertSame('delivery_failed', $order->fresh()->status);

        $sync->apply($order->fresh(), 'delivered');
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('delivered', $order->fresh()->shipping_status);
    }

    public function test_returned_order_is_terminal_when_late_ghn_event_arrives(): void
    {
        $order = $this->order(['status' => 'returned', 'shipping_status' => 'returned']);

        app(GHNOrderSyncService::class)->apply($order, 'delivering');

        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame('returned', $order->fresh()->shipping_status);
    }

    public function test_stale_or_unknown_ghn_status_cannot_regress_shipping_state(): void
    {
        $order = $this->order(['status' => 'shipping', 'shipping_status' => 'shipping']);
        $sync = app(GHNOrderSyncService::class);

        $sync->apply($order, 'ready_to_pick');
        $this->assertSame('shipping', $order->fresh()->status);
        $this->assertSame('shipping', $order->fresh()->shipping_status);

        $sync->apply($order->fresh(), 'provider_added_a_new_status');
        $this->assertSame('shipping', $order->fresh()->status);
        $this->assertSame('shipping', $order->fresh()->shipping_status);
    }

    public function test_partial_return_review_marker_is_not_overwritten_by_later_automatic_events(): void
    {
        $order = $this->order(['status' => 'shipping', 'shipping_status' => 'partial_return']);

        app(GHNOrderSyncService::class)->apply($order, 'delivered');

        $this->assertSame('shipping', $order->fresh()->status);
        $this->assertSame('partial_return', $order->fresh()->shipping_status);
    }

    public function test_partial_return_event_cannot_regress_a_completed_order(): void
    {
        $order = $this->order(['status' => 'completed', 'shipping_status' => 'delivered']);

        $result = app(GHNOrderSyncService::class)->applyPartialReturn($order, 'returned', 'GHN-LATE-PARTIAL');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('delivered', $order->fresh()->shipping_status);
        $this->assertSame('delivered', $result['shipping_status']);
    }

    public function test_partial_return_requires_review_without_returning_order_or_restocking_every_item(): void
    {
        $category = Category::create(['name' => 'Đồ bơi GHN', 'slug' => 'do-boi-ghn']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Bikini GHN', 'slug' => 'bikini-ghn', 'price' => 200000, 'description' => 'Sản phẩm kiểm thử', 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'Xanh', 'size' => 'M', 'stock' => 3]);
        $order = $this->order(['status' => 'shipping', 'shipping_status' => 'delivering']);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'color' => 'Xanh',
            'size' => 'M',
            'price' => 200000,
            'quantity' => 2,
            'subtotal' => 400000,
        ]);

        $payload = array_merge($this->payload('returned'), [
            'IsPartialReturn' => true,
            'PartialReturnCode' => 'GHN-PARTIAL-001',
        ]);

        $this->withHeader('X-GHN-Webhook-Secret', 'ghn-webhook-secret-for-tests')
            ->postJson(route('ghn.webhook'), $payload)
            ->assertOk()
            ->assertJsonPath('shipping_status', 'partial_return')
            ->assertJsonPath('requires_manual_review', true);

        $this->assertSame('shipping', $order->fresh()->status);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_admin_dashboard_surfaces_partial_return_and_filter_opens_only_review_orders(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $review = $this->order(['order_code' => 'VB-NEEDS-REVIEW', 'shipping_status' => 'partial_return']);
        $this->order(['order_code' => 'VB-NORMAL-SHIPMENT', 'ghn_order_code' => 'GHN-WEBHOOK-002']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1 đơn GHN hoàn một phần')
            ->assertSee(route('admin.orders.index', ['shipping_review' => 1]), false);

        $this->actingAs($admin)->get(route('admin.orders.index', ['shipping_review' => 1]))
            ->assertOk()
            ->assertSee($review->order_code)
            ->assertDontSee('VB-NORMAL-SHIPMENT');
    }

    private function order(array $attributes = []): Order
    {
        return Order::create($attributes + [
            'order_code' => 'VB-GHN-WEBHOOK',
            'customer_name' => 'Khách GHN',
            'email' => 'ghn@example.test',
            'phone' => '0900000000',
            'address' => 'Đà Nẵng',
            'subtotal_amount' => 200000,
            'shipping_fee' => 30000,
            'total_amount' => 230000,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'shipping_status' => 'pending',
            'ghn_order_code' => 'GHN-WEBHOOK-001',
        ]);
    }

    private function payload(string $status): array
    {
        return [
            'OrderCode' => 'GHN-WEBHOOK-001',
            'ClientOrderCode' => 'VB-GHN-WEBHOOK',
            'ShopID' => 123456,
            'Status' => $status,
            'Type' => 'Switch_status',
            'Time' => '2026-09-07T01:00:00Z',
            'PartialReturnCode' => '',
            'Description' => 'GHN cập nhật trạng thái',
        ];
    }
}
