<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabAdminFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        return Order::create($attributes + ['order_code' => 'VB-'.uniqid(), 'customer_name' => 'Khách thử', 'email' => 'customer@example.test', 'phone' => '0900000000', 'address' => 'Hà Nội', 'total_amount' => 150000, 'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'pending', 'shipping_status' => 'pending']);
    }

    public function test_reports_count_orders_once_and_exclude_refunds_and_cancelled_orders(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $paid = $this->order(['payment_status' => 'paid']);
        $paid->paymentTransactions()->create(['gateway' => 'cod', 'amount' => 150000, 'status' => 'paid']);
        $paid->paymentTransactions()->create(['gateway' => 'momo', 'amount' => 150000, 'status' => 'failed']);
        $refunded = $this->order(['payment_status' => 'refund_pending']);
        $refunded->paymentTransactions()->create(['gateway' => 'cod', 'amount' => 150000, 'status' => 'paid']);
        $this->order(['status' => 'cancelled', 'payment_status' => 'paid']);
        $this->get(route('admin.reports.index'))->assertOk()->assertViewHas('totalRevenue', 150000);
        $this->get(route('admin.reports.charts'))->assertOk()->assertSee('30 ngày gần nhất');
        $this->get(route('admin.finance.index'))->assertOk()->assertViewHas('summary', fn ($s) => $s->order_count === 3 && (int) $s->total_amount === 450000);
        $this->get(route('admin.finance.transactions', ['payment_status' => 'paid']))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 2);
    }

    public function test_cod_update_rejects_stale_form_and_online_payment(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $order = $this->order();
        $payment = $order->paymentTransactions()->create(['gateway' => 'cod', 'amount' => 150000, 'status' => 'pending']);
        $payload = ['payment_status' => 'paid', 'current_payment_status' => 'pending', 'current_order_status' => 'pending', 'current_payment_id' => $payment->id];
        $this->patch(route('admin.finance.update-status', $order), $payload)->assertSessionHasNoErrors();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->patch(route('admin.finance.update-status', $order), $payload)->assertSessionHasErrors('payment_status');
        $online = $this->order(['payment_method' => 'momo']);
        $payload['current_payment_id'] = 0;
        $this->patch(route('admin.finance.update-status', $online), $payload)->assertSessionHasErrors('payment_status');
    }

    public function test_existing_completed_cod_and_shipping_tabs_are_reported_correctly(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $order = $this->order(['status' => 'completed', 'shipping_status' => 'delivered', 'payment_status' => 'paid']);
        $order->paymentTransactions()->create(['gateway' => 'cod', 'amount' => 150000, 'status' => 'pending']);
        $this->order();
        $this->get(route('admin.reports.index'))->assertOk()->assertViewHas('totalRevenue', 150000);
        $this->get(route('admin.orders.index', ['tab' => 'delivered']))->assertOk()->assertViewHas('orders', fn ($rows) => $rows->total() === 1)->assertViewHas('counts', fn ($counts) => $counts->sum() === 2);
        $this->get(route('admin.orders.index', ['sort' => 'invalid']))->assertSessionHasErrors('sort');
        $this->patch(route('admin.finance.update-status', $order), ['payment_status' => 'refund_pending', 'current_payment_status' => 'paid', 'current_order_status' => 'completed', 'current_payment_id' => $order->paymentTransactions()->first()->id])->assertSessionHasNoErrors();
        $this->assertSame('refund_pending', $order->fresh()->payment_status);
    }

    public function test_cod_cannot_collect_cancelled_order_and_refund_is_recorded_once(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $cancelled = $this->order(['status' => 'cancelled']);
        $this->patch(route('admin.finance.update-status', $cancelled), ['payment_status' => 'paid', 'current_payment_status' => 'pending', 'current_order_status' => 'cancelled', 'current_payment_id' => 0])->assertSessionHasErrors('payment_status');
        $order = $this->order(['payment_status' => 'refund_pending']);
        $payment = $order->paymentTransactions()->create(['gateway' => 'cod', 'amount' => 150000, 'status' => 'refund_pending']);
        $payload = ['payment_status' => 'refunded', 'current_payment_status' => 'refund_pending', 'current_order_status' => 'pending', 'current_payment_id' => $payment->id];
        $this->patch(route('admin.finance.update-status', $order), $payload)->assertSessionHasNoErrors();
        $this->patch(route('admin.finance.update-status', $order), $payload)->assertSessionHasErrors('payment_status');
        $this->assertSame(1, $order->paymentTransactions()->where('type', 'refund')->count());
    }

    public function test_finance_filters_export_and_validation(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->order(['customer_name' => '=SUM(1,2)', 'order_code' => 'VB-EXPORT']);
        $this->order(['total_amount' => 100]);
        $this->get(route('admin.finance.transactions', ['min_amount' => 1000]))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
        $this->get(route('admin.finance.index', ['date_from' => '2026-09-20', 'date_to' => '2026-09-01']))->assertSessionHasErrors('date_to');
        $this->get(route('admin.finance.index', ['min_amount' => 500, 'max_amount' => 100]))->assertSessionHasErrors('max_amount');
        $csv = $this->get(route('admin.finance.export', ['search' => 'VB-EXPORT']))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM", $csv);
    }

    public function test_account_crud_preserves_order_history_and_reverification(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->get(route('admin.users.create'))->assertOk();
        $data = ['name' => 'Khách mới', 'username' => 'labcustomer', 'email' => 'lab@example.test', 'password' => 'SecurePassword!123', 'password_confirmation' => 'SecurePassword!123', 'role' => 'customer'];
        $this->post(route('admin.users.store'), $data)->assertSessionHasNoErrors();
        $user = User::where('username', 'labcustomer')->firstOrFail();
        $this->get(route('admin.users.show', $user))->assertOk();
        $this->get(route('admin.users.edit', $user))->assertOk();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->put(route('admin.users.update', $user), array_merge($data, ['email' => 'changed@example.test']))->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email_verified_at);
        $order = $this->order(['user_id' => $user->id]);
        $this->delete(route('admin.users.destroy', $user))->assertSessionHasErrors('user');
        $this->assertNotNull($order->fresh());
        $empty = User::factory()->create();
        $this->delete(route('admin.users.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('users', ['id' => $empty->id]);
        $this->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('user');
    }

    public function test_customers_cannot_access_finance_reports_or_account_administration(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['admin.reports.index', 'admin.reports.charts', 'admin.finance.index', 'admin.finance.transactions', 'admin.finance.export', 'admin.users.create'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
}
