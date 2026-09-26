<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->string('type', 30)->default('payment');
            $table->string('gateway', 30);
            $table->string('gateway_order_id', 120)->nullable();
            $table->string('gateway_request_id', 120)->nullable()->index();
            $table->string('transaction_id', 120)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('pending');
            $table->integer('result_code')->nullable();
            $table->string('response_code', 30)->nullable();
            $table->string('bank_code', 50)->nullable();
            $table->string('message', 500)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_order_id']);
            // Failed gateway callbacks may reuse sentinel transaction IDs such as "0".
            $table->index(['gateway', 'transaction_id']);
            $table->index(['order_id', 'status']);
            $table->index(['order_id', 'gateway', 'created_at']);
        });

        DB::table('orders')->orderBy('id')->chunkById(500, function ($orders): void {
            $rows = $orders->map(function ($order): array {
                $gateway = in_array($order->payment_method, ['cod', 'momo', 'vnpay'], true)
                    ? $order->payment_method
                    : 'cod';
                $status = match ($order->payment_status) {
                    'paid', 'failed', 'refund_pending', 'refunded' => $order->payment_status,
                    default => 'pending',
                };

                return [
                    'order_id' => $order->id,
                    'type' => 'payment',
                    'gateway' => $gateway,
                    'gateway_order_id' => $order->order_code,
                    'gateway_request_id' => $gateway === 'momo' ? $order->momo_request_id : null,
                    'transaction_id' => match ($gateway) {
                        'momo' => $order->momo_trans_id,
                        'vnpay' => $order->vnpay_transaction_no,
                        default => null,
                    },
                    'amount' => $order->total_amount,
                    'status' => $status,
                    'result_code' => $gateway === 'momo' && is_numeric($order->momo_result_code) ? (int) $order->momo_result_code : null,
                    'response_code' => $gateway === 'vnpay' ? $order->vnpay_response_code : null,
                    'bank_code' => $gateway === 'vnpay' ? $order->vnpay_bank_code : null,
                    'message' => 'Giao dịch được chuyển từ dữ liệu đơn hàng hiện có.',
                    'paid_at' => match ($gateway) {
                        'momo' => $order->momo_paid_at,
                        'vnpay' => $order->vnpay_paid_at,
                        default => null,
                    },
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ];
            })->all();

            DB::table('payment_transactions')->insert($rows);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
