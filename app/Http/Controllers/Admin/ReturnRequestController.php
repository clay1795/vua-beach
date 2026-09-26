<?php

namespace App\Http\Controllers\Admin;

use App\Mail\ReturnRequestUpdatedMail;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Services\InventoryService;
use App\Services\MailFailureAlert;
use App\Services\OrderStateMachine;
use App\Services\ReturnStateMachine;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReturnRequestController extends AdminController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $returns = ReturnRequest::with('order', 'user')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.returns.index', compact('returns'));
    }

    public function show(ReturnRequest $returnRequest)
    {
        $this->authorizeAdmin();
        $returnRequest->load('order', 'user', 'items.orderItem.product.variants', 'items.replacementVariant', 'histories.changedBy');

        return view('admin.returns.show', compact('returnRequest'));
    }

    public function update(Request $request, ReturnRequest $returnRequest)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,receive,complete,cancel'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'replacement_variant_id' => ['nullable', 'array'],
            'replacement_variant_id.*' => ['nullable', 'integer', 'exists:product_variants,id'],
        ]);

        DB::transaction(function () use ($returnRequest, $data, $request) {
            $returnRequest = ReturnRequest::lockForUpdate()->with('order', 'items.orderItem')->findOrFail($returnRequest->id);
            match ($data['action']) {
                'approve' => $this->approve($returnRequest, $data, $request->user()->id),
                'reject' => app(ReturnStateMachine::class)->transition($returnRequest, 'rejected', 'admin', $request->user()->id, $data['admin_note'] ?? 'Yêu cầu đổi trả đã bị từ chối.', ['admin_note' => $data['admin_note'] ?? null]),
                'receive' => $this->receive($returnRequest, $data['admin_note'] ?? null, $request->user()->id),
                'complete' => $this->complete($returnRequest, $data['admin_note'] ?? null, $request->user()->id),
                'cancel' => $this->cancelApproved($returnRequest, $data['admin_note'] ?? null, $request->user()->id),
            };
        });

        $returnRequest->refresh()->load('order');
        $this->audit('return_request.updated', $returnRequest, 'Cập nhật yêu cầu đổi trả #'.$returnRequest->id.' sang trạng thái '.$returnRequest->status.'.', ['action' => $data['action']]);
        try {
            Mail::to($returnRequest->user->email)->queue(new ReturnRequestUpdatedMail($returnRequest));
        } catch (\Throwable $exception) {
            app(MailFailureAlert::class)->report('mail_enqueue_failed', ReturnRequestUpdatedMail::class, $exception, ['return_id' => $returnRequest->id]);
        }

        return back()->with('success', 'Đã cập nhật yêu cầu đổi trả.');
    }

    private function approve(ReturnRequest $return, array $data, int $adminId): void
    {
        if (! app(ReturnStateMachine::class)->canTransition($return->status, 'approved')) {
            $this->rejectUpdate('Không thể duyệt yêu cầu ở trạng thái hiện tại.');
        }
        foreach ($return->items as $item) {
            if ($return->type !== 'exchange') {
                continue;
            }
            $replacementId = $data['replacement_variant_id'][$item->id] ?? null;
            $replacement = ProductVariant::lockForUpdate()->find($replacementId);
            if (! $replacement || ! $replacement->is_active || $replacement->product_id !== $item->orderItem->product_id || $replacement->stock < $item->quantity) {
                $this->rejectUpdate('Biến thể đổi size không hợp lệ hoặc không đủ tồn kho.');
            }
            app(InventoryService::class)->applyOnce($replacement->id, -$item->quantity, "return:{$return->id}:item:{$item->id}:replacement-reserve", 'exchange_replacement_reserved', $return->order_id, 'Giữ hàng đổi size cho yêu cầu #'.$return->id);
            $item->update(['replacement_variant_id' => $replacement->id]);
        }
        app(ReturnStateMachine::class)->transition($return, 'approved', 'admin', $adminId, 'Yêu cầu đã được duyệt.', ['approved_at' => now(), 'admin_note' => $data['admin_note'] ?? null]);
    }

    private function receive(ReturnRequest $return, ?string $note, int $adminId): void
    {
        if (! app(ReturnStateMachine::class)->canTransition($return->status, 'received')) {
            $this->rejectUpdate('Chỉ có thể nhận hàng cho yêu cầu đã được duyệt.');
        }
        foreach ($return->items as $item) {
            if (! $item->orderItem->product_variant_id) {
                continue;
            }
            app(InventoryService::class)->applyOnce($item->orderItem->product_variant_id, $item->quantity, "return:{$return->id}:item:{$item->id}:original-received", 'return_received', $return->order_id, 'Đã nhận hàng hoàn cho yêu cầu #'.$return->id);
        }
        app(ReturnStateMachine::class)->transition($return, 'received', 'admin', $adminId, 'Cửa hàng đã nhận hàng hoàn và cập nhật tồn kho.', ['received_at' => now(), 'admin_note' => $note ?? $return->admin_note]);
    }

    private function complete(ReturnRequest $return, ?string $note, int $adminId): void
    {
        if (! app(ReturnStateMachine::class)->canTransition($return->status, 'completed')) {
            $this->rejectUpdate('Chỉ có thể hoàn tất sau khi cửa hàng đã nhận hàng hoàn.');
        }
        $attributes = ['completed_at' => now(), 'admin_note' => $note ?? $return->admin_note];
        if ($return->type === 'refund' && ! $return->refund_processed_at) {
            $order = $return->order()->lockForUpdate()->firstOrFail();
            $refundableTotal = max(0, (int) $order->subtotal_amount - min((int) $order->subtotal_amount, (int) $order->discount_amount));
            $remainingAmount = max(0, $refundableTotal - (int) $order->refunded_amount);
            if ((int) $return->refund_amount > $remainingAmount) {
                $this->rejectUpdate('Số tiền hoàn vượt quá số tiền sản phẩm khách đã thanh toán còn lại.');
            }
            $refundedAmount = (int) $order->refunded_amount + (int) $return->refund_amount;
            $order->update(['refunded_amount' => $refundedAmount]);
            if ($order->payment_status === 'paid' && $refundedAmount >= $refundableTotal) {
                app(OrderStateMachine::class)->transitionPayment($order, 'refunded');
            }
            $attributes['refund_processed_at'] = now();
        }
        app(ReturnStateMachine::class)->transition($return, 'completed', 'admin', $adminId, $return->type === 'exchange' ? 'Đổi size đã hoàn tất.' : 'Hoàn hàng và ghi nhận hoàn tiền đã hoàn tất.', $attributes);
    }

    private function cancelApproved(ReturnRequest $return, ?string $note, int $adminId): void
    {
        if (! app(ReturnStateMachine::class)->canTransition($return->status, 'cancelled')) {
            $this->rejectUpdate('Không thể hủy yêu cầu ở trạng thái hiện tại.');
        }
        if ($return->type === 'exchange') {
            foreach ($return->items as $item) {
                if (! $item->replacement_variant_id || ! ($variant = ProductVariant::lockForUpdate()->find($item->replacement_variant_id))) {
                    continue;
                }
                app(InventoryService::class)->applyOnce($variant->id, $item->quantity, "return:{$return->id}:item:{$item->id}:replacement-release", 'exchange_reservation_released', $return->order_id, 'Hủy giữ hàng đổi size cho yêu cầu #'.$return->id);
            }
        }
        app(ReturnStateMachine::class)->transition($return, 'cancelled', 'admin', $adminId, 'Yêu cầu đổi trả đã bị hủy và hàng giữ đã được hoàn lại.', ['admin_note' => $note ?? $return->admin_note]);
    }

    private function rejectUpdate(string $message): never
    {
        throw new HttpResponseException(
            redirect()->back()->withErrors(['action' => $message])
        );
    }
}
