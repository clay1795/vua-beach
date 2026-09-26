<?php

namespace App\Http\Controllers\Admin;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\FinanceOrders;
use App\Services\GHNOrderSyncService;
use App\Services\GHNService;
use App\Services\InventoryService;
use App\Services\MailFailureAlert;
use App\Services\OrderStateMachine;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends AdminController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $query = $this->filteredOrders($request);
        $tabs = ['all' => 'Tất cả'] + Order::SHIPPING_STATUS_LABELS;
        $counts = (clone $query)->select('shipping_status')->selectRaw('COUNT(*) as total')->groupBy('shipping_status')->pluck('total', 'shipping_status');
        $activeTab = $request->input('tab', 'all') ?: 'all';
        if ($activeTab !== 'all') {
            $query->where('shipping_status', $activeTab);
        }
        [$column, $direction] = match ($request->input('sort')) {
            'oldest' => ['created_at', 'asc'], 'amount_asc' => ['total_amount', 'asc'], 'amount_desc' => ['total_amount', 'desc'], default => ['created_at', 'desc'],
        };
        $orders = $query->with('user')->orderBy($column, $direction)->orderBy('id', $direction)->paginate((int) ($request->input('per_page') ?: 25))->withQueryString();

        return view('admin.orders.index', compact('orders', 'tabs', 'counts', 'activeTab'));
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin();
        $orders = $this->filteredOrders($request)->latest();
        if ($request->filled('tab') && $request->input('tab') !== 'all') {
            $orders->where('shipping_status', $request->input('tab'));
        }

        return response()->streamDownload(function () use ($orders) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Mã đơn', 'Khách hàng', 'Email', 'Số điện thoại', 'Tổng tiền', 'Trạng thái', 'Mã GHN', 'Ngày đặt']);
            foreach ($orders->cursor() as $order) {
                fputcsv($stream, [
                    $this->safeCsvCell($order->order_code),
                    $this->safeCsvCell($order->customer_name),
                    $this->safeCsvCell($order->email),
                    $this->safeCsvCell($order->phone),
                    $order->total_amount,
                    $this->safeCsvCell($order->status),
                    $this->safeCsvCell($order->ghn_order_code),
                    $order->created_at->format('Y-m-d H:i'),
                ]);
            }
            fclose($stream);
        }, 'don-hang-vua-beach-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Order $order)
    {
        $this->authorizeAdmin();

        return view('admin.orders.show', ['order' => $order->load('items.product', 'user', 'statusHistories.changedBy', 'paymentTransactions')]);
    }

    public function update(Request $request, Order $order, GHNService $ghn)
    {
        $this->authorizeAdmin();
        $data = $request->validate(['status' => 'required|in:pending,confirmed,shipping,completed,delivery_failed,returning,returned,cancelled']);
        $newStatus = $data['status'];
        try {
            $result = Cache::lock('order-transition:'.$order->id, 45)->block(10, function () use ($order, $newStatus, $ghn, $request) {
                $freshOrder = Order::query()->with('items')->findOrFail($order->id);
                $oldStatus = $freshOrder->status;
                if (! app(OrderStateMachine::class)->canTransitionFor($freshOrder, $newStatus)) {
                    throw ValidationException::withMessages(['status' => "Không thể chuyển đơn hàng từ {$oldStatus} sang {$newStatus}."]);
                }

                $ghnOrderCode = null;
                if ($newStatus === 'confirmed' && $oldStatus === 'pending' && blank($freshOrder->ghn_order_code) && $ghn->configured()) {
                    $shipment = $ghn->createOrder($freshOrder);
                    if (($shipment['code'] ?? 0) !== 200 || blank($shipment['data']['order_code'] ?? null)) {
                        throw ValidationException::withMessages(['status' => $shipment['message'] ?? 'GHN chưa thể tạo vận đơn cho đơn hàng này.']);
                    }
                    $ghnOrderCode = $shipment['data']['order_code'];
                }

                $transitionResult = DB::transaction(function () use ($freshOrder, $newStatus, $ghnOrderCode, $request) {
                    $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($freshOrder->id);
                    $transitionFrom = $lockedOrder->status;
                    if (! app(OrderStateMachine::class)->canTransitionFor($lockedOrder, $newStatus)) {
                        throw ValidationException::withMessages(['status' => "Không thể chuyển đơn hàng từ {$lockedOrder->status} sang {$newStatus}."]);
                    }
                    if ($newStatus === 'cancelled') {
                        if ($lockedOrder->payment_method !== 'cod' && $lockedOrder->payment_status === 'pending') {
                            throw ValidationException::withMessages([
                                'status' => 'Giao dịch online vẫn đang chờ kết quả. Chỉ hủy sau khi cổng thanh toán báo thất bại hoặc đã ghi nhận hoàn tiền.',
                            ]);
                        }
                        if ($lockedOrder->payment_status === 'paid') {
                            app(OrderStateMachine::class)->transitionPayment($lockedOrder, 'refund_pending');
                        }
                        foreach ($lockedOrder->items as $item) {
                            $variant = $this->resolveVariant($item, true);
                            if ($variant) {
                                app(InventoryService::class)->applyOnce($variant->id, $item->quantity, "order:{$lockedOrder->id}:variant:{$variant->id}:release", 'order_cancelled', $lockedOrder->id, 'Hủy đơn '.$lockedOrder->order_code);
                            }
                        }
                    }
                    $changed = app(OrderStateMachine::class)->transition(
                        $lockedOrder,
                        $newStatus,
                        'admin',
                        $request->user()->id,
                        $ghnOrderCode ? 'Đã xác nhận đơn và tạo vận đơn GHN '.$ghnOrderCode.'.' : 'Quản trị viên cập nhật trạng thái đơn.',
                        $ghnOrderCode ? ['ghn_order_code' => $ghnOrderCode] : [],
                    );
                    if ($newStatus === 'completed' && $lockedOrder->payment_method === 'cod') {
                        app(OrderStateMachine::class)->transitionPayment($lockedOrder, 'paid');
                    }

                    return ['changed' => $changed, 'oldStatus' => $transitionFrom];
                });

                return [
                    'changed' => $transitionResult['changed'],
                    'oldStatus' => $transitionResult['oldStatus'],
                    'ghnOrderCode' => $ghnOrderCode,
                ];
            });
        } catch (LockTimeoutException) {
            return back()->withErrors(['status' => 'Đơn hàng đang được xử lý ở một yêu cầu khác. Vui lòng thử lại.']);
        }

        $oldStatus = $result['oldStatus'];
        $ghnOrderCode = $result['ghnOrderCode'];
        if ($result['changed']) {
            $this->audit('order.status_updated', $order, 'Cập nhật trạng thái đơn '.$order->order_code.' từ '.$oldStatus.' sang '.$newStatus.'.', ['old_status' => $oldStatus, 'new_status' => $newStatus, 'ghn_order_code' => $ghnOrderCode]);
            try {
                Mail::to($order->email)->queue(new OrderStatusUpdatedMail($order->fresh()));
            } catch (\Throwable $exception) {
                app(MailFailureAlert::class)->report('mail_enqueue_failed', OrderStatusUpdatedMail::class, $exception, ['order_id' => $order->id]);
            }
        }

        return back()->with('success', $ghnOrderCode
            ? 'Đã tạo vận đơn GHN: '.$ghnOrderCode.' và cập nhật trạng thái đơn hàng.'
            : ($newStatus === 'cancelled' && $order->fresh()->payment_status === 'refund_pending'
                ? 'Đã hủy đơn và hoàn kho. Hãy hoàn tiền thực tế rồi xác nhận trên hệ thống.'
                : 'Đã cập nhật trạng thái đơn hàng.'));
    }

    public function confirmRefund(Request $request, Order $order)
    {
        $this->authorizeAdmin();
        try {
            $changed = Cache::lock('order-transition:'.$order->id, 45)->block(10, function () use ($order): bool {
                return DB::transaction(function () use ($order): bool {
                    $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                    if ($lockedOrder->status !== 'cancelled' || ! in_array($lockedOrder->payment_status, ['refund_pending', 'refunded'], true)) {
                        throw ValidationException::withMessages([
                            'refund' => 'Chỉ xác nhận hoàn tiền cho đơn đã hủy đang chờ hoàn tiền.',
                        ]);
                    }
                    if ($lockedOrder->payment_status === 'refunded') {
                        return false;
                    }

                    return app(OrderStateMachine::class)->transitionPayment($lockedOrder, 'refunded', [
                        'refunded_amount' => max((int) $lockedOrder->refunded_amount, (int) $lockedOrder->total_amount),
                    ]);
                });
            });
        } catch (LockTimeoutException) {
            return back()->withErrors(['refund' => 'Đơn hàng đang được xử lý ở một yêu cầu khác. Vui lòng thử lại.']);
        }

        if ($changed) {
            $this->audit('order.refund_confirmed', $order, 'Xác nhận đã hoàn tiền cho đơn bị hủy '.$order->order_code.'.', [
                'amount' => (int) $order->fresh()->refunded_amount,
            ]);
            try {
                Mail::to($order->email)->queue(new OrderStatusUpdatedMail($order->fresh()));
            } catch (\Throwable $exception) {
                app(MailFailureAlert::class)->report('mail_enqueue_failed', OrderStatusUpdatedMail::class, $exception, ['order_id' => $order->id]);
            }
        }

        return back()->with('success', $changed ? 'Đã xác nhận hoàn tiền cho khách hàng.' : 'Đơn hàng đã được xác nhận hoàn tiền trước đó.');
    }

    public function syncGhn(Order $order, GHNService $ghn, GHNOrderSyncService $syncService)
    {
        $this->authorizeAdmin();
        if (blank($order->ghn_order_code)) {
            return back()->withErrors(['ghn' => 'Đơn này chưa có mã vận đơn GHN.']);
        }
        $result = $ghn->orderDetail($order->ghn_order_code);
        $details = $result['data'] ?? [];
        // GHN can return either an object-like payload or a one-item list,
        // depending on the environment/version of the API.
        if (isset($details[0]) && is_array($details[0])) {
            $details = $details[0];
        }

        if (($result['code'] ?? 0) !== 200 || blank($details['status'] ?? null)) {
            return back()->withErrors(['ghn' => $result['message'] ?? 'Không thể đồng bộ trạng thái GHN.']);
        }
        $ghnStatus = (string) $details['status'];
        $synced = $syncService->apply($order, $ghnStatus);
        $this->audit('order.ghn_synced', $order, 'Đồng bộ vận đơn GHN cho đơn '.$order->order_code.'.', ['ghn_status' => $ghnStatus]);
        $message = 'Đã đồng bộ GHN: '.$ghnStatus.'.';
        if ($synced['order_changed']) {
            $message .= ' Trạng thái đơn đã chuyển sang '.$synced['order_status'].'.';
        }

        return back()->with('success', $message);
    }

    private function resolveVariant($item, bool $lock = false): ?ProductVariant
    {
        $query = ProductVariant::query();
        if ($lock) {
            $query->lockForUpdate();
        }

        if ($item->product_variant_id && ($variant = $query->find($item->product_variant_id))) {
            return $variant;
        }

        return ProductVariant::query()
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->where('product_id', $item->product_id)
            ->where('color', $item->color)
            ->where('size', $item->size)
            ->first();
    }

    private function safeCsvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Spreadsheet apps may execute a user-controlled cell as a formula. A leading
        // apostrophe forces text while preserving the value visible to the operator.
        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function filteredOrders(Request $request): Builder
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,confirmed,shipping,completed,delivery_failed,returning,returned,cancelled'],
            'shipping_review' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'gateway' => ['nullable', 'in:cod,momo,vnpay'],
            'payment_status' => ['nullable', Rule::in(array_keys(Order::PAYMENT_STATUS_LABELS))],
            'tab' => ['nullable', Rule::in(array_merge(['all'], array_keys(Order::SHIPPING_STATUS_LABELS)))],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'sort' => ['nullable', 'in:newest,oldest,amount_asc,amount_desc'],
        ]);

        return Order::query()
            ->when(filled($filters['gateway'] ?? null), fn (Builder $query) => $query->where('payment_method', $filters['gateway']))
            ->when(filled($filters['payment_status'] ?? null), fn (Builder $query) => $query->whereIn('id', app(FinanceOrders::class)->query()->where('finance_status', $filters['payment_status'])->select('id')))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when((bool) ($filters['shipping_review'] ?? false), fn (Builder $query) => $query->where('shipping_status', 'partial_return'))
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when(filled($filters['q'] ?? null), function (Builder $query) use ($filters): void {
                $search = $filters['q'];
                $query->where(fn (Builder $inner) => $inner
                    ->where('order_code', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('ghn_order_code', 'like', '%'.$search.'%')
                    ->orWhereHas('items.product', fn (Builder $products) => $products->where('name', 'like', '%'.$search.'%'))
                    ->orWhere('email', 'like', '%'.$search.'%'));
            });
    }
}
