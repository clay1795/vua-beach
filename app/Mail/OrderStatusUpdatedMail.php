<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800];

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function build(): self
    {
        return $this->subject('Cập nhật đơn '.$this->order->order_code.' từ Vua Beach')
            ->view('emails.orders.status');
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('mail')->error('Order status email exhausted retries', ['order_id' => $this->order->id, 'exception' => $exception::class]);
    }
}
