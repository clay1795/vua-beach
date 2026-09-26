<?php

namespace Tests\Feature;

use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Mail\ReturnRequestUpdatedMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailRecoveryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_requeue_order_mail_by_code_or_id(): void
    {
        Mail::fake();
        $order = Order::create([
            'order_code' => 'VB-MAILRECOVERY',
            'customer_name' => 'Khách',
            'email' => 'customer@example.test',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'total_amount' => 300000,
            'payment_method' => 'cod',
        ]);

        $this->artisan('mail:resend-business', ['type' => 'order-placed', 'id' => $order->order_code])
            ->expectsOutput('Đã đưa email order-placed cho đơn VB-MAILRECOVERY vào queue.')
            ->assertSuccessful();
        $this->artisan('mail:resend-business', ['type' => 'order-status', 'id' => (string) $order->id])
            ->assertSuccessful();

        Mail::assertQueued(OrderPlacedMail::class, 1);
        Mail::assertQueued(OrderStatusUpdatedMail::class, 1);
    }

    public function test_operator_can_requeue_return_mail_and_invalid_type_is_rejected(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'order_code' => 'VB-RETURNMAIL',
            'customer_name' => $user->name,
            'email' => $user->email,
            'phone' => '0900000000',
            'address' => 'Đà Nẵng',
            'total_amount' => 300000,
            'payment_method' => 'cod',
        ]);
        $return = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'type' => 'refund',
            'reason' => 'Sản phẩm không phù hợp',
            'status' => 'requested',
        ]);

        $this->artisan('mail:resend-business', ['type' => 'return-status', 'id' => (string) $return->id])
            ->assertSuccessful();
        $this->artisan('mail:resend-business', ['type' => 'unknown', 'id' => '1'])
            ->expectsOutput('Loại email không hợp lệ. Dùng: order-placed, order-status hoặc return-status.')
            ->assertExitCode(Command::INVALID);

        Mail::assertQueued(ReturnRequestUpdatedMail::class, 1);
    }
}
