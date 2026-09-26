<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\FinanceOrders;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceController extends AdminController
{
    public const METHODS = ['cod' => 'COD', 'momo' => 'MoMo', 'vnpay' => 'VNPAY'];

    public const COD_TRANSITIONS = [
        'unpaid' => ['pending', 'paid', 'failed'], 'pending' => ['pending', 'paid', 'failed'],
        'failed' => ['failed', 'pending', 'paid'], 'paid' => ['paid', 'refund_pending'],
        'refund_pending' => ['refund_pending', 'refunded'], 'refunded' => ['refunded'], 'cancelled' => ['cancelled'],
    ];

    private function ordersQuery()
    {
        return app(FinanceOrders::class)->query();
    }

    private function filteredOrders(Request $request): array
    {
        $this->authorizeAdmin();
        $filters = $request->validate([
            'search' => 'nullable|string|max:100', 'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'min_amount' => 'nullable|numeric|min:0|max:9999999999999',
            'max_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', ...($request->filled('min_amount') ? ['gte:min_amount'] : [])],
            'gateway' => ['nullable', Rule::in(array_keys(self::METHODS))],
            'payment_status' => ['nullable', Rule::in(array_keys(PaymentTransaction::STATUS_LABELS + ['unpaid' => 'Chưa thanh toán']))],
            'sort' => 'nullable|in:newest,oldest,amount_asc,amount_desc', 'page' => 'nullable|integer|min:1',
        ], ['date_to.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.', 'max_amount.gte' => 'Số tiền tối đa phải lớn hơn hoặc bằng số tiền tối thiểu.']);
        $query = $this->ordersQuery();
        if ($request->filled('search')) {
            $search = '%'.trim($filters['search']).'%';
            $query->where(fn ($q) => $q->where('order_code', 'like', $search)->orWhere('customer_name', 'like', $search)->orWhere('phone', 'like', $search));
        }
        foreach (['gateway' => 'gateway', 'payment_status' => 'finance_status'] as $key => $column) {
            if ($request->filled($key)) {
                $query->where($column, $filters[$key]);
            }
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $filters['date_from'].' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<', Carbon::parse($filters['date_to'])->addDay()->startOfDay());
        }
        if ($request->filled('min_amount')) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }
        if ($request->filled('max_amount')) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }

        return [$query, $filters];
    }

    public function index(Request $request)
    {
        [$query, $filters] = $this->filteredOrders($request);

        return view('admin.finance.index', [
            'filters' => $filters, 'methods' => self::METHODS, 'statuses' => PaymentTransaction::STATUS_LABELS + ['unpaid' => 'Chưa thanh toán'],
            'summary' => (clone $query)->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount),0) as total_amount')->first(),
            'statusTotals' => (clone $query)->select('finance_status')->selectRaw('COUNT(*) as order_count, SUM(total_amount) as total_amount')->groupBy('finance_status')->get(),
            'methodTotals' => (clone $query)->select('gateway')->selectRaw('COUNT(*) as order_count, SUM(total_amount) as total_amount')->groupBy('gateway')->get(),
        ]);
    }

    public function transactions(Request $request)
    {
        [$query, $filters] = $this->filteredOrders($request);
        [$column, $direction] = match ($filters['sort'] ?? 'newest') {
            'oldest' => ['created_at', 'asc'], 'amount_asc' => ['total_amount', 'asc'], 'amount_desc' => ['total_amount', 'desc'], default => ['created_at', 'desc'],
        };

        return view('admin.finance.transactions', [
            'orders' => $query->orderBy($column, $direction)->orderBy('id', $direction)->paginate(15)->withQueryString(),
            'filters' => $filters, 'methods' => self::METHODS, 'statuses' => PaymentTransaction::STATUS_LABELS + ['unpaid' => 'Chưa thanh toán'], 'codTransitions' => self::COD_TRANSITIONS,
        ]);
    }

    public function export(Request $request)
    {
        [$query] = $this->filteredOrders($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Mã đơn', 'Người nhận', 'Điện thoại', 'Phương thức', 'Trạng thái', 'Tổng tiền']);
            foreach ($query->orderBy('id')->cursor() as $order) {
                $cells = [$order->order_code, $order->customer_name, $order->phone, $order->gateway, $order->finance_status, $order->total_amount];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[\x00-\x20]*[=+@-]/u', (string) $v) ? "'".$v : $v, $cells));
            }
            fclose($out);
        }, 'tai-chinh.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'payment_status' => ['required', Rule::in(array_keys(self::COD_TRANSITIONS))],
            'current_payment_status' => 'required|string', 'current_order_status' => 'required|string', 'current_payment_id' => 'required|integer|min:0',
        ]);
        DB::transaction(function () use ($order, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = $locked->paymentTransactions()->where('type', 'payment')->orderByRaw(FinanceOrders::PRIORITY)->orderByDesc('id')->lockForUpdate()->first();
            $current = in_array($locked->payment_status, ['refund_pending', 'refunded'], true) ? $locked->payment_status : ($payment?->status ?? $locked->payment_status);
            if ($locked->payment_method === 'cod' && $locked->payment_status === 'paid' && ! in_array($payment?->status, ['refund_pending', 'refunded'], true)) {
                $current = 'paid';
            }
            $fail = fn ($message) => throw ValidationException::withMessages(['payment_status' => $message]);
            if ($locked->payment_method !== 'cod' || ($payment && $payment->gateway !== 'cod')) {
                $fail('Chỉ được cập nhật thủ công cho đơn COD.');
            }
            if ($current !== $data['current_payment_status'] || $locked->status !== $data['current_order_status'] || (int) ($payment?->id ?? 0) !== (int) $data['current_payment_id']) {
                $fail('Đơn hàng vừa thay đổi. Vui lòng tải lại trang.');
            }
            $target = $data['payment_status'];
            if (! in_array($target, self::COD_TRANSITIONS[$current] ?? [], true)) {
                $fail('Không thể chuyển sang trạng thái thanh toán này.');
            }
            if ($target === $current) {
                return;
            }
            if (in_array($target, ['pending', 'paid'], true) && (in_array($locked->status, ['cancelled', 'returning', 'returned'], true) || in_array($locked->shipping_status, ['cancelled', 'return', 'returning', 'return_transporting', 'returned', 'partial_return'], true))) {
                $fail('Không thể xác nhận thu tiền cho đơn đã hủy hoặc hoàn hàng.');
            }
            $attributes = ['status' => $target, 'message' => 'Quản trị viên #'.auth()->id().' cập nhật COD.', 'paid_at' => $target === 'paid' ? ($payment?->paid_at ?? now()) : $payment?->paid_at];
            if ($payment) {
                $payment->update($attributes);
            } else {
                $payment = $locked->paymentTransactions()->create($attributes + ['gateway' => 'cod', 'amount' => $locked->total_amount]);
            }
            $remainingRefund = max(0, (int) $locked->total_amount - (int) $locked->refunded_amount);
            $locked->update(['payment_status' => $target] + ($target === 'refunded' ? ['refunded_amount' => $locked->total_amount] : []));
            if ($target === 'refunded') {
                $locked->paymentTransactions()->create(['parent_transaction_id' => $payment->id, 'type' => 'refund', 'gateway' => 'cod', 'amount' => $remainingRefund, 'status' => 'refunded', 'message' => 'Hoàn COD được quản trị viên xác nhận.']);
            }
            $this->audit('finance.cod_updated', $locked, 'Cập nhật thanh toán COD.', ['from' => $current, 'to' => $target]);
        });

        return back()->with('success', 'Đã cập nhật trạng thái thanh toán COD.');
    }
}
