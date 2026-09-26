<?php

namespace App\Console\Commands;

use App\Mail\OrderPlacedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Mail\ReturnRequestUpdatedMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\MailFailureAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ResendBusinessMail extends Command
{
    protected $signature = 'mail:resend-business
        {type : order-placed, order-status hoặc return-status}
        {id : ID database; riêng order có thể dùng mã đơn}';

    protected $description = 'Đưa lại một email nghiệp vụ cụ thể vào queue sau khi xử lý lỗi hạ tầng';

    public function handle(): int
    {
        $type = (string) $this->argument('type');
        if (! in_array($type, ['order-placed', 'order-status', 'return-status'], true)) {
            $this->error('Loại email không hợp lệ. Dùng: order-placed, order-status hoặc return-status.');

            return self::INVALID;
        }

        try {
            [$recipient, $mail, $reference] = match ($type) {
                'order-placed' => $this->orderMail(true),
                'order-status' => $this->orderMail(false),
                'return-status' => $this->returnMail(),
            };
            Mail::to($recipient)->queue($mail);
        } catch (Throwable $exception) {
            app(MailFailureAlert::class)->report('mail_manual_enqueue_failed', $type, $exception, [
                'reference' => (string) $this->argument('id'),
            ]);
            $this->error('Không thể đưa email vào queue. Cảnh báo vận hành đã được ghi nhận.');

            return self::FAILURE;
        }

        $this->info("Đã đưa email {$type} cho {$reference} vào queue.");

        return self::SUCCESS;
    }

    /** @return array{string, OrderPlacedMail|OrderStatusUpdatedMail, string} */
    private function orderMail(bool $placed): array
    {
        $identifier = (string) $this->argument('id');
        $order = Order::query()
            ->when(ctype_digit($identifier), fn ($query) => $query->whereKey((int) $identifier), fn ($query) => $query->where('order_code', $identifier))
            ->with('items')
            ->firstOrFail();

        return [
            $order->email,
            $placed ? new OrderPlacedMail($order) : new OrderStatusUpdatedMail($order),
            'đơn '.$order->order_code,
        ];
    }

    /** @return array{string, ReturnRequestUpdatedMail, string} */
    private function returnMail(): array
    {
        $return = ReturnRequest::query()->with('user', 'order')->findOrFail((int) $this->argument('id'));

        return [
            $return->user->email,
            new ReturnRequestUpdatedMail($return),
            'yêu cầu đổi trả #'.$return->id,
        ];
    }
}
