<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestHistory;
use App\Models\ReturnRequestItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnRequestController extends Controller
{
    public function create(Request $request, Order $order)
    {
        if (! $this->isEligible($request, $order)) {
            return redirect()->route('orders.show', $order)->withErrors([
                'return' => 'Chỉ có thể yêu cầu đổi trả trong 7 ngày sau khi đơn hàng hoàn thành.',
            ]);
        }

        if ($this->hasActiveRequest($order)) {
            return redirect()->route('orders.show', $order)->withErrors([
                'return' => 'Đơn hàng này đã có yêu cầu đổi trả đang được xử lý.',
            ]);
        }

        $order->load('items.product.variants');
        $claimedQuantities = $this->claimedQuantities($order);
        $order->items->each(function ($item) use ($claimedQuantities): void {
            $item->setAttribute('returnable_quantity', max(0, $item->quantity - (int) ($claimedQuantities[$item->id] ?? 0)));
        });
        if ($order->items->every(fn ($item) => $item->returnable_quantity === 0)) {
            return redirect()->route('orders.show', $order)->withErrors([
                'return' => 'Toàn bộ sản phẩm trong đơn đã được gửi yêu cầu đổi/trả trước đó.',
            ]);
        }

        return view('returns.create', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        if (! $this->isEligible($request, $order)) {
            return redirect()->route('orders.show', $order)->withErrors([
                'return' => 'Chỉ có thể yêu cầu đổi trả trong 7 ngày sau khi đơn hàng hoàn thành.',
            ]);
        }
        $data = $request->validate([
            'type' => ['required', 'in:refund,exchange'],
            'reason' => ['required', 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.desired_size' => ['nullable', 'string', 'max:20'],
        ], [
            'type.required' => 'Vui lòng chọn hình thức xử lý.',
            'type.in' => 'Hình thức xử lý không hợp lệ.',
            'reason.required' => 'Vui lòng chọn lý do đổi trả.',
            'reason.max' => 'Lý do đổi trả không được vượt quá 255 ký tự.',
            'items.required' => 'Vui lòng chọn ít nhất một sản phẩm cần đổi/trả.',
            'items.array' => 'Danh sách sản phẩm đổi/trả không hợp lệ.',
            'items.min' => 'Vui lòng chọn ít nhất một sản phẩm cần đổi/trả.',
            'items.*.order_item_id.required' => 'Sản phẩm đổi/trả không hợp lệ.',
            'items.*.order_item_id.exists' => 'Sản phẩm đổi/trả không thuộc đơn hàng này.',
            'items.*.quantity.required' => 'Vui lòng nhập số lượng cần đổi/trả.',
            'items.*.quantity.min' => 'Số lượng đổi/trả phải lớn hơn 0.',
        ]);

        $order->load('items.product.variants');
        $selectedItems = collect($data['items']);
        foreach ($selectedItems as $itemData) {
            $orderItem = $order->items->firstWhere('id', (int) $itemData['order_item_id']);
            if (! $orderItem || $itemData['quantity'] > $orderItem->quantity) {
                $this->reject('Số lượng yêu cầu đổi trả không hợp lệ.');
            }
            if ($data['type'] === 'exchange' && blank($itemData['desired_size'])) {
                $this->reject('Vui lòng chọn size mong muốn cho sản phẩm cần đổi.');
            }
        }

        DB::transaction(function () use ($request, $order, $data, $selectedItems) {
            $order = Order::with('items')->lockForUpdate()->findOrFail($order->id);
            $exists = $order->returnRequests()->whereIn('status', ReturnRequest::ACTIVE_STATUSES)->exists();
            if ($exists) {
                $this->reject('Đơn hàng này đã có yêu cầu đổi trả đang được xử lý.');
            }

            $claimedQuantities = $this->claimedQuantities($order);

            foreach ($selectedItems as $itemData) {
                $orderItem = $order->items->firstWhere('id', (int) $itemData['order_item_id']);
                $alreadyClaimed = (int) ($claimedQuantities[$orderItem->id] ?? 0);
                if ($alreadyClaimed + (int) $itemData['quantity'] > $orderItem->quantity) {
                    $this->reject('Số lượng đổi/trả vượt quá số lượng còn lại của sản phẩm.');
                }
            }

            $grossSubtotal = max(0, (int) ($order->subtotal_amount ?: $order->items->sum('subtotal')));
            $netMerchandiseTotal = max(0, $grossSubtotal - min($grossSubtotal, (int) $order->discount_amount));
            $selectedGross = $selectedItems->sum(function ($itemData) use ($order) {
                $orderItem = $order->items->firstWhere('id', (int) $itemData['order_item_id']);

                return (int) $orderItem->price * (int) $itemData['quantity'];
            });
            $allRemainingItemsSelected = $order->items->every(function ($orderItem) use ($claimedQuantities, $selectedItems) {
                $selectedQuantity = (int) ($selectedItems->firstWhere('order_item_id', $orderItem->id)['quantity'] ?? 0);

                return (int) ($claimedQuantities[$orderItem->id] ?? 0) + $selectedQuantity >= $orderItem->quantity;
            });
            $refundAmount = $grossSubtotal > 0
                ? (int) round($selectedGross * $netMerchandiseTotal / $grossSubtotal)
                : 0;
            if ($allRemainingItemsSelected) {
                $refundAmount = max(0, $netMerchandiseTotal - (int) $order->refunded_amount);
            }

            $return = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'type' => $data['type'],
                'reason' => $data['reason'],
                'customer_note' => $data['customer_note'] ?? null,
                'refund_amount' => $data['type'] === 'refund'
                    ? $refundAmount
                    : 0,
            ]);
            foreach ($selectedItems as $itemData) {
                $return->items()->create([
                    'order_item_id' => $itemData['order_item_id'],
                    'quantity' => $itemData['quantity'],
                    'desired_size' => $data['type'] === 'exchange' ? $itemData['desired_size'] : null,
                ]);
            }
            ReturnRequestHistory::create([
                'return_request_id' => $return->id,
                'status' => 'requested',
                'source' => 'customer',
                'changed_by_user_id' => $request->user()->id,
                'note' => 'Khách hàng đã gửi yêu cầu đổi trả.',
            ]);
        });

        return redirect()->route('orders.show', $order)->with('success', 'Đã gửi yêu cầu đổi trả. Cửa hàng sẽ phản hồi sớm nhất có thể.');
    }

    private function isEligible(Request $request, Order $order): bool
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return $order->status === 'completed'
            && $order->completed_at
            && $order->completed_at->gte(now()->subDays(ReturnRequest::ELIGIBILITY_DAYS));
    }

    private function hasActiveRequest(Order $order): bool
    {
        return $order->returnRequests()->whereIn('status', ReturnRequest::ACTIVE_STATUSES)->exists();
    }

    private function claimedQuantities(Order $order)
    {
        return ReturnRequestItem::query()
            ->whereIn('order_item_id', $order->items->pluck('id'))
            ->whereHas('request', fn ($query) => $query->whereNotIn('status', ['rejected', 'cancelled']))
            ->selectRaw('order_item_id, SUM(quantity) AS claimed_quantity')
            ->groupBy('order_item_id')
            ->pluck('claimed_quantity', 'order_item_id');
    }

    private function reject(string $message): never
    {
        throw new HttpResponseException(
            redirect()->back()->withErrors(['return' => $message])
        );
    }
}
