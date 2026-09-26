<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\WebhookConflictException;
use App\Http\Controllers\Controller;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\CouponRedemptionService;
use App\Services\GHNService;
use App\Services\InventoryService;
use App\Services\MailFailureAlert;
use App\Services\OrderShipmentService;
use App\Services\OrderStateMachine;
use App\Services\VnpayService;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VnpayController extends Controller
{
    public function return(Request $request, VnpayService $vnpay)
    {
        $data = $request->all();
        $wellFormed = $this->callbackIsWellFormed($data);
        $match = $wellFormed ? $this->matchingTransaction($data, $vnpay) : null;
        $order = $match[0] ?? null;
        $valid = (bool) $match;
        // VNPAY chỉ được xem là thanh toán thành công khi cả hai mã đều là 00.
        // Không tin riêng ResponseCode vì TransactionStatus có thể vẫn báo giao dịch chưa hoàn tất.
        $success = $valid
            && $request->string('vnp_ResponseCode')->toString() === '00'
            && $request->string('vnp_TransactionStatus')->toString() === '00'
            && $this->matchesAmount($order, $request->input('vnp_Amount'));

        if ($success) {
            $message = $order->payment_status === 'paid'
                ? 'VNPAY đã xác nhận thanh toán thành công.'
                : 'VNPAY đã nhận kết quả. Đơn hàng sẽ được xác nhận khi hệ thống nhận IPN từ VNPAY.';

            return $this->redirectForOrder($request, $order)->with('success', $message);
        }

        return $this->redirectForOrder($request, $order)->withErrors(['payment' => $valid ? 'Giao dịch VNPAY chưa hoàn tất hoặc đã bị hủy.' : 'Không thể xác thực kết quả thanh toán VNPAY.']);
    }

    public function ipn(Request $request, VnpayService $vnpay, GHNService $ghn, OrderShipmentService $shipments)
    {
        $data = $request->all();
        if (! $this->callbackIsWellFormed($data)) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid request']);
        }

        $match = $this->matchingTransaction($data, $vnpay);
        if (! $match) {
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
        }
        [$order, $transaction] = $match;
        if (! $this->matchesAmount($order, $data['vnp_Amount'] ?? null)) {
            return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
        }
        $transaction = $this->persistLegacyTransaction($order, $transaction);
        $eventKey = implode(':', [
            $data['vnp_TxnRef'],
            $data['vnp_TransactionNo'] ?? 'none',
            $data['vnp_ResponseCode'] ?? 'none',
            $data['vnp_TransactionStatus'] ?? 'none',
        ]);
        try {
            $result = app(WebhookIdempotencyService::class)->process('vnpay', $eventKey, $data, function () use ($order, $transaction, $data, $ghn, $shipments) {
                if (($data['vnp_ResponseCode'] ?? null) !== '00' || ($data['vnp_TransactionStatus'] ?? '00') !== '00') {
                    $this->releaseReservation($order, $transaction, $data, 'VNPAY phản hồi giao dịch không thành công.');

                    return ['code' => 200, 'body' => ['RspCode' => '00', 'Message' => 'Confirm Success']];
                }

                $this->markPaid($order, $transaction, $data, $ghn, $shipments);

                return ['code' => 200, 'body' => ['RspCode' => '00', 'Message' => 'Confirm Success']];
            });
        } catch (WebhookConflictException) {
            Log::channel('payment')->warning('VNPAY IPN event key conflict.', ['order_id' => $order->id, 'event_fingerprint' => $this->eventFingerprint($eventKey)]);

            return response()->json(['RspCode' => '99', 'Message' => 'Conflicting event'], 409);
        }
        Log::channel('payment')->info('VNPAY IPN handled', ['order_id' => $order->id, 'payment_transaction_id' => $transaction->id, 'event_fingerprint' => $this->eventFingerprint($eventKey), 'response_code' => $data['vnp_ResponseCode'] ?? null, 'replayed' => $result['replayed']]);

        return response()->json($result['body'], $result['code']);
    }

    private function markPaid(Order $order, PaymentTransaction $transaction, array $data, GHNService $ghn, OrderShipmentService $shipments): void
    {
        $outcome = DB::transaction(function () use ($order, $transaction, $data) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $lockedTransaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($lockedTransaction->status === 'paid'
                && in_array($lockedOrder->payment_status, ['paid', 'refund_pending', 'refunded'], true)) {
                return 'unchanged';
            }
            $alreadySettled = in_array($lockedOrder->payment_status, ['paid', 'refund_pending', 'refunded'], true);
            $lockedTransaction->update([
                'transaction_id' => $data['vnp_TransactionNo'] ?? null,
                'status' => $alreadySettled ? 'refund_pending' : 'paid',
                'response_code' => $data['vnp_ResponseCode'] ?? null,
                'bank_code' => $data['vnp_BankCode'] ?? null,
                'message' => 'VNPAY xác nhận thanh toán thành công.',
                'response_payload' => $this->safePayload($data),
                'paid_at' => now(),
            ]);
            if ($alreadySettled) {
                return 'duplicate_payment';
            }
            if ($lockedOrder->payment_status === 'failed') {
                app(OrderStateMachine::class)->transitionPayment($lockedOrder, 'pending');
            }
            app(OrderStateMachine::class)->transitionPayment($lockedOrder, 'paid', [
                'vnpay_transaction_no' => $data['vnp_TransactionNo'] ?? null,
                'vnpay_bank_code' => $data['vnp_BankCode'] ?? null,
                'vnpay_response_code' => $data['vnp_ResponseCode'] ?? null,
                'vnpay_paid_at' => now(),
            ]);

            if ($lockedOrder->status === 'cancelled') {
                app(OrderStateMachine::class)->transitionPayment($lockedOrder, 'refund_pending');

                return 'refund_pending';
            }

            app(CouponRedemptionService::class)->consumeForOrder($lockedOrder);

            return 'paid';
        });
        if ($outcome === 'unchanged') {
            return;
        }
        if (in_array($outcome, ['refund_pending', 'duplicate_payment'], true)) {
            Log::channel('payment')->warning('VNPAY payment requires refund review.', [
                'order_id' => $order->id,
                'payment_transaction_id' => $transaction->id,
                'reason' => $outcome,
            ]);
        } else {
            $shipments->create($order->fresh(), $ghn, 'vnpay');
        }
        try {
            Mail::to($order->email)->queue(new OrderStatusUpdatedMail($order->fresh()));
        } catch (\Throwable $exception) {
            app(MailFailureAlert::class)->report('mail_enqueue_failed', OrderStatusUpdatedMail::class, $exception, ['order_id' => $order->id]);
        }
    }

    private function releaseReservation(Order $order, PaymentTransaction $transaction, array $data, string $note): void
    {
        DB::transaction(function () use ($order, $transaction, $data, $note) {
            $lockedOrder = Order::lockForUpdate()->with('items')->findOrFail($order->id);
            $lockedTransaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $lockedTransaction->update([
                'transaction_id' => $data['vnp_TransactionNo'] ?? null,
                'status' => 'failed',
                'response_code' => $data['vnp_ResponseCode'] ?? null,
                'bank_code' => $data['vnp_BankCode'] ?? null,
                'message' => $note,
                'response_payload' => $this->safePayload($data),
            ]);
            if ($lockedOrder->payment_method !== 'vnpay' || $lockedOrder->payment_status === 'paid' || $lockedOrder->status !== 'pending') {
                return;
            }

            foreach ($lockedOrder->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }
                app(InventoryService::class)->applyOnce($item->product_variant_id, $item->quantity, "order:{$lockedOrder->id}:variant:{$item->product_variant_id}:release", 'vnpay_payment_failed', $lockedOrder->id, 'Hoàn tồn kho do VNPAY không thành công: '.$lockedOrder->order_code);
            }

            app(OrderStateMachine::class)->transitionPayment($lockedOrder, 'failed', ['vnpay_response_code' => $data['vnp_ResponseCode'] ?? null]);
            app(OrderStateMachine::class)->transition($lockedOrder, 'cancelled', 'vnpay', null, $note);
        });
    }

    private function matchesAmount(Order $order, mixed $amount): bool
    {
        return (int) $amount === (int) $order->total_amount * 100;
    }

    /** @return array{0:Order,1:PaymentTransaction}|null */
    private function matchingTransaction(array $data, VnpayService $vnpay): ?array
    {
        if (! $vnpay->configured() || ! $vnpay->validSignature($data)) {
            return null;
        }

        $transaction = PaymentTransaction::query()
            ->with('order')
            ->where('gateway', 'vnpay')
            ->where('gateway_order_id', $data['vnp_TxnRef'] ?? null)
            ->first();
        if ($transaction) {
            $order = $transaction->order;
        } else {
            $order = Order::query()
                ->where('order_code', $data['vnp_TxnRef'] ?? null)
                ->where('payment_method', 'vnpay')
                ->first();
            if (! $order) {
                return null;
            }
            $attributes = [
                'gateway' => 'vnpay',
                'gateway_order_id' => (string) $data['vnp_TxnRef'],
                'amount' => $order->total_amount,
                'status' => 'initiated',
                'message' => 'Giao dịch VNPAY cũ được nhập vào sổ giao dịch.',
            ];
            $transaction = $order->paymentTransactions()->make($attributes);
        }

        if ($order->payment_method !== 'vnpay'
            || (int) $transaction->amount !== (int) $order->total_amount) {
            return null;
        }

        return [$order, $transaction];
    }

    private function persistLegacyTransaction(Order $order, PaymentTransaction $transaction): PaymentTransaction
    {
        if ($transaction->exists) {
            return $transaction;
        }

        return $order->paymentTransactions()->firstOrCreate(
            ['gateway' => 'vnpay', 'gateway_order_id' => $transaction->gateway_order_id],
            Arr::except($transaction->getAttributes(), ['id', 'order_id', 'gateway', 'gateway_order_id']),
        );
    }

    private function eventFingerprint(string $eventKey): string
    {
        return substr(hash('sha256', $eventKey), 0, 16);
    }

    private function safePayload(array $payload): array
    {
        unset($payload['vnp_SecureHash'], $payload['vnp_SecureHashType']);

        return $payload;
    }

    private function callbackIsWellFormed(array $data): bool
    {
        return ! Validator::make($data, [
            'vnp_TmnCode' => ['required', 'string', 'max:50', Rule::in([(string) config('services.vnpay.tmn_code')])],
            'vnp_TxnRef' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'vnp_Amount' => ['required', 'integer', 'min:0'],
            'vnp_ResponseCode' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'vnp_TransactionStatus' => ['required', 'string', 'size:2', 'regex:/^\d{2}$/'],
            'vnp_TransactionNo' => ['nullable', 'string', 'max:100'],
            'vnp_BankCode' => ['nullable', 'string', 'max:30'],
            'vnp_SecureHash' => ['required', 'string', 'size:128', 'regex:/^[A-Fa-f0-9]{128}$/'],
        ])->fails();
    }

    private function redirectForOrder(Request $request, ?Order $order)
    {
        if ($order && $request->user() && ($request->user()->id === $order->user_id || $request->user()->is_admin)) {
            return redirect()->route('orders.show', $order);
        }

        return redirect()->route('home');
    }
}
