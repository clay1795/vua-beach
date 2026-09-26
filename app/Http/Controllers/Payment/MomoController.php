<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\WebhookConflictException;
use App\Http\Controllers\Controller;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\CouponRedemptionService;
use App\Services\GHNService;
use App\Services\MailFailureAlert;
use App\Services\MomoService;
use App\Services\OrderShipmentService;
use App\Services\OrderStateMachine;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MomoController extends Controller
{
    public function return(Request $request, MomoService $momo)
    {
        $data = $request->all();
        if (! $this->callbackIsWellFormed($data)) {
            return redirect()->route('home')->withErrors(['payment' => 'Không thể xác thực kết quả thanh toán MoMo.']);
        }
        $failed = (int) $data['resultCode'] !== 0;
        $match = $this->matchingTransaction($data, $momo);
        if (! $match) {
            return redirect()->route('home')->withErrors(['payment' => 'Không thể xác thực kết quả thanh toán MoMo.']);
        }

        [$order] = $match;
        if ($order->status === 'cancelled') {
            return $this->redirectForOrder($request, $order)->with('payment_notice', 'cancelled');
        }
        if ($failed) {
            if (! $match[1]->exists) {
                $match = $this->matchingTransaction($data, $momo, true);
            }
            $this->markFailed($order, $match[1], $data);

            return $this->redirectForOrder($request, $order)->with('payment_notice', 'failed');
        }
        $message = $order->payment_status === 'paid'
            ? 'MoMo đã xác nhận thanh toán thành công.'
            : 'MoMo đã nhận kết quả. Đơn hàng sẽ được xác nhận khi hệ thống nhận IPN từ MoMo.';

        return $this->redirectForOrder($request, $order)->with('success', $message);
    }

    public function payAgain(Request $request, Order $order, MomoService $momo)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        if (! $momo->configured()) {
            return back()->withErrors(['payment' => 'Thanh toán MoMo chưa được cấu hình đầy đủ.']);
        }

        $transaction = DB::transaction(function () use ($order): PaymentTransaction {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payment_method !== 'momo'
                || $locked->status !== 'pending'
                || $locked->payment_status !== 'failed') {
                throw ValidationException::withMessages([
                    'payment' => 'Đơn hàng này không thể thanh toán lại bằng MoMo.',
                ]);
            }

            app(OrderStateMachine::class)->transitionPayment($locked, 'pending', [
                'momo_payment_status' => 'pending',
            ]);

            return $locked->paymentTransactions()->create([
                'gateway' => 'momo',
                'amount' => $locked->total_amount,
                'status' => 'pending',
                'message' => 'Khách hàng thanh toán lại.',
            ]);
        });

        try {
            $payment = $momo->createPayment($order->fresh(), $transaction, $request);

            $fresh = $order->fresh();
            if ($fresh->payment_status !== 'pending') {
                $transaction->update([
                    'status' => 'cancelled',
                    'message' => 'Không chuyển khách sang MoMo vì đơn đã được thanh toán ở lần khác.',
                ]);

                return redirect()->route('orders.show', $fresh)
                    ->with('success', 'Đơn hàng đã được ghi nhận thanh toán ở một giao dịch khác.');
            }

            return redirect()->away($payment['pay_url']);
        } catch (\Throwable $exception) {
            DB::transaction(function () use ($order): void {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->payment_status === 'pending') {
                    app(OrderStateMachine::class)->transitionPayment($locked, 'failed', [
                        'momo_payment_status' => 'creation_failed',
                    ]);
                }
            });

            return redirect()->route('orders.show', $order)
                ->withErrors(['payment' => $exception->getMessage().' Bạn có thể thử lại sau.']);
        }
    }

    public function ipn(Request $request, MomoService $momo, GHNService $ghn, OrderShipmentService $shipments)
    {
        $data = $request->all();
        if (! $this->callbackIsWellFormed($data)) {
            return response()->json(['message' => 'Invalid request'], 400);
        }
        $match = $this->matchingTransaction($data, $momo, true);
        if (! $match) {
            return response()->json(['message' => 'Invalid request'], 400);
        }

        [$order, $transaction] = $match;
        $eventKey = implode(':', [$data['orderId'], $data['requestId'], $data['transId'] ?? 'none', $data['resultCode']]);
        try {
            $result = app(WebhookIdempotencyService::class)->process('momo', $eventKey, $data, function () use ($order, $transaction, $data, $ghn, $shipments) {
                if ((string) $data['resultCode'] === '0') {
                    $this->markPaid($order, $transaction, $data, $ghn, $shipments);
                } else {
                    $this->markFailed($order, $transaction, $data);
                }

                return ['code' => 200, 'body' => ['message' => 'OK']];
            });
        } catch (WebhookConflictException) {
            Log::channel('payment')->warning('MoMo IPN event key conflict.', ['order_id' => $order->id, 'event_fingerprint' => $this->eventFingerprint($eventKey)]);

            return response()->json(['message' => 'Conflicting event'], 409);
        }
        Log::channel('payment')->info('MoMo IPN handled', ['order_id' => $order->id, 'payment_transaction_id' => $transaction->id, 'event_fingerprint' => $this->eventFingerprint($eventKey), 'result_code' => (string) $data['resultCode'], 'replayed' => $result['replayed']]);

        return response()->json($result['body'], $result['code']);
    }

    /** @return array{0:Order,1:PaymentTransaction}|null */
    private function matchingTransaction(array $data, MomoService $momo, bool $importLegacyAttempt = false): ?array
    {
        if (! $momo->configured() || ! $momo->validSignature($data)) {
            return null;
        }

        $transaction = PaymentTransaction::query()
            ->with('order')
            ->where('gateway', 'momo')
            ->where('gateway_order_id', $data['orderId'] ?? null)
            ->where('gateway_request_id', $data['requestId'] ?? null)
            ->first();

        if ($transaction) {
            $order = $transaction->order;
        } else {
            // Compatibility for MoMo attempts created before the transaction ledger existed.
            $order = Order::query()
                ->where('order_code', $data['orderId'] ?? null)
                ->where('momo_request_id', $data['requestId'] ?? null)
                ->first();
            if (! $order) {
                return null;
            }
            if ($order->payment_method !== 'momo'
                || (string) $data['partnerCode'] !== (string) config('services.momo.partner_code')
                || (int) ($data['amount'] ?? -1) !== (int) $order->total_amount) {
                return null;
            }
            $attributes = [
                'gateway' => 'momo',
                'gateway_order_id' => (string) $data['orderId'],
                'gateway_request_id' => (string) $data['requestId'],
                'amount' => $order->total_amount,
                'status' => 'initiated',
                'message' => 'Giao dịch MoMo cũ được nhập vào sổ giao dịch.',
            ];
            $transaction = $importLegacyAttempt
                ? $order->paymentTransactions()->firstOrCreate(
                    ['gateway' => 'momo', 'gateway_order_id' => (string) $data['orderId']],
                    [
                        'gateway_request_id' => (string) $data['requestId'],
                        'amount' => $order->total_amount,
                        'status' => 'initiated',
                        'message' => 'Giao dịch MoMo cũ được nhập vào sổ giao dịch.',
                    ],
                )
                : $order->paymentTransactions()->make($attributes);
        }

        if ($order->payment_method !== 'momo'
            || (string) $data['partnerCode'] !== (string) config('services.momo.partner_code')
            || (int) ($data['amount'] ?? -1) !== (int) $order->total_amount
            || (int) $transaction->amount !== (int) $order->total_amount) {
            return null;
        }

        return [$order, $transaction];
    }

    private function markPaid(Order $order, PaymentTransaction $transaction, array $data, GHNService $ghn, OrderShipmentService $shipments): void
    {
        $outcome = DB::transaction(function () use ($order, $transaction, $data) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedTransaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($lockedTransaction->status === 'paid'
                && in_array($locked->payment_status, ['paid', 'refund_pending', 'refunded'], true)) {
                return 'unchanged';
            }

            $alreadySettled = in_array($locked->payment_status, ['paid', 'refund_pending', 'refunded'], true);

            $lockedTransaction->update([
                'transaction_id' => (string) $data['transId'],
                'status' => $alreadySettled ? 'refund_pending' : 'paid',
                'result_code' => (int) $data['resultCode'],
                'message' => (string) $data['message'],
                'response_payload' => $this->safePayload($data),
                'paid_at' => now(),
            ]);

            if ($alreadySettled) {
                return 'duplicate_payment';
            }

            if ($locked->payment_status === 'failed') {
                app(OrderStateMachine::class)->transitionPayment($locked, 'pending');
            }
            app(OrderStateMachine::class)->transitionPayment($locked, 'paid', [
                'momo_payment_status' => 'paid',
                'momo_trans_id' => (string) $data['transId'],
                'momo_result_code' => (string) $data['resultCode'],
                'momo_paid_at' => now(),
            ]);

            if ($locked->status === 'cancelled') {
                if ($locked->payment_status === 'paid') {
                    app(OrderStateMachine::class)->transitionPayment($locked, 'refund_pending');
                }

                return 'refund_pending';
            }

            app(CouponRedemptionService::class)->consumeForOrder($locked);

            return 'paid';
        });
        if ($outcome === 'unchanged') {
            return;
        }
        if (in_array($outcome, ['refund_pending', 'duplicate_payment'], true)) {
            Log::channel('payment')->warning('MoMo payment requires refund review.', [
                'order_id' => $order->id,
                'payment_transaction_id' => $transaction->id,
                'reason' => $outcome,
            ]);
        } else {
            $shipments->create($order->fresh(), $ghn, 'momo');
        }
        try {
            Mail::to($order->email)->queue(new OrderStatusUpdatedMail($order->fresh()));
        } catch (\Throwable $exception) {
            app(MailFailureAlert::class)->report('mail_enqueue_failed', OrderStatusUpdatedMail::class, $exception, ['order_id' => $order->id]);
        }
    }

    private function markFailed(Order $order, PaymentTransaction $transaction, array $data): void
    {
        DB::transaction(function () use ($order, $transaction, $data): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedTransaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($lockedTransaction->status === 'paid') {
                return;
            }

            $lockedTransaction->update([
                'transaction_id' => filled($data['transId'] ?? null) ? (string) $data['transId'] : null,
                'status' => 'failed',
                'result_code' => isset($data['resultCode']) ? (int) $data['resultCode'] : null,
                'message' => (string) ($data['message'] ?? 'Thanh toán MoMo không thành công.'),
                'response_payload' => $this->safePayload($data),
            ]);

            $latestTransactionId = $locked->paymentTransactions()->max('id');
            if ($latestTransactionId === $lockedTransaction->id
                && $locked->payment_status === 'pending') {
                app(OrderStateMachine::class)->transitionPayment($locked, 'failed', [
                    'momo_payment_status' => 'failed',
                    'momo_result_code' => (string) ($data['resultCode'] ?? ''),
                ]);
            }
        });
    }

    private function redirectForOrder(Request $request, Order $order)
    {
        return $request->user() && ($request->user()->id === $order->user_id || $request->user()->is_admin)
            ? redirect()->route('orders.show', $order) : redirect()->route('home');
    }

    private function eventFingerprint(string $eventKey): string
    {
        return substr(hash('sha256', $eventKey), 0, 16);
    }

    private function safePayload(array $payload): array
    {
        unset($payload['signature']);

        return $payload;
    }

    private function callbackIsWellFormed(array $data): bool
    {
        return ! Validator::make($data, [
            'partnerCode' => ['required', 'string', 'max:50', Rule::in([(string) config('services.momo.partner_code')])],
            'requestId' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:0'],
            'orderId' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'orderInfo' => ['required', 'string', 'max:255'],
            'orderType' => ['required', 'string', 'max:50'],
            'transId' => ['required', $this->scalarIdentifierRule(100)],
            'resultCode' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:255'],
            'payType' => ['required', 'string', 'max:50'],
            'responseTime' => ['required', $this->scalarIdentifierRule(30)],
            'extraData' => ['present', 'nullable', 'string', 'max:2048'],
            'signature' => ['required', 'string', 'size:64', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ])->fails();
    }

    private function scalarIdentifierRule(int $maximumLength): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($maximumLength): void {
            if ((! is_string($value) && ! is_int($value))
                || strlen((string) $value) > $maximumLength
                || preg_match('/^[A-Za-z0-9_-]+$/', (string) $value) !== 1) {
                $fail("The {$attribute} field is invalid.");
            }
        };
    }
}
