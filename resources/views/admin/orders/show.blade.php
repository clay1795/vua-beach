@extends('layouts.admin')
@section('page-title', 'Chi tiết đơn hàng')
@section('content')
<a href="{{ route('admin.orders.index') }}" class="d-inline-flex align-items-center gap-2 mb-4 text-decoration-none fw-bold"><i class="bi bi-arrow-left"></i>Danh sách đơn</a>
@if($order->payment_status === 'refund_pending')
<div class="alert alert-warning" role="alert"><strong>Đơn đã hủy nhưng chưa xác nhận hoàn tiền.</strong> Hãy hoàn tiền qua đúng giao dịch gốc, đối chiếu thành công rồi mới bấm xác nhận bên dưới.</div>
@endif
@if($order->paymentTransactions->contains('status', 'refund_pending'))
<div class="alert alert-danger" role="alert"><strong>Phát hiện thanh toán trùng.</strong> Có một giao dịch thành công sau khi đơn đã nhận tiền. Hãy đối chiếu mã giao dịch trong lịch sử thanh toán và hoàn khoản thu trùng qua MoMo.</div>
@endif
@if($order->shipping_status === 'partial_return')
<div class="alert alert-warning" role="alert"><strong>GHN báo hoàn một phần.</strong> Hãy đối chiếu hàng thực nhận và xử lý đúng từng sản phẩm trong nghiệp vụ đổi/trả; hệ thống chưa tự cộng tồn kho.</div>
@endif
<div class="row g-4">
    <div class="col-xl-8"><section class="panel p-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><small class="muted text-uppercase fw-bold">Mã đơn hàng</small><h2 class="h4 fw-bold mb-0">{{ $order->order_code }}</h2></div><span class="status status-{{ $order->status }}">{{ $order->statusLabel() }}</span></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Sản phẩm</th><th>Phân loại</th><th>Số lượng</th><th class="text-end">Thành tiền</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td><div class="d-flex align-items-center gap-2">@if($item->product_image_url || $item->product?->image_url)<img src="{{ $item->product_image_url ?: $item->product?->image_url }}" alt="{{ $item->product_name }}" width="42" height="42" class="rounded object-fit-cover">@endif<span class="fw-bold">{{ $item->product_name }}</span></div></td><td>{{ $item->color }} · {{ $item->size }}</td><td>{{ $item->quantity }}</td><td class="text-end fw-bold">{{ number_format($item->subtotal, 0, ',', '.') }}đ</td></tr>@endforeach</tbody></table></div><div class="pt-3 mt-3 border-top small"><div class="d-flex justify-content-between"><span class="muted">Tiền hàng</span><b>{{ number_format($order->subtotal_amount ?: ($order->total_amount - $order->shipping_fee), 0, ',', '.') }}đ</b></div>@if($order->discount_amount)<div class="d-flex justify-content-between mt-2"><span class="muted">Ưu đãi {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</span><b class="text-success">-{{ number_format($order->discount_amount, 0, ',', '.') }}đ</b></div>@endif<div class="d-flex justify-content-between mt-2"><span class="muted">Phí giao hàng</span><b>{{ number_format($order->shipping_fee, 0, ',', '.') }}đ</b></div><div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top"><span class="muted">Tổng thanh toán</span><strong class="fs-4">{{ number_format($order->total_amount, 0, ',', '.') }}đ</strong></div></div></section></div>
    <div class="col-xl-4"><section class="panel p-4"><h2 class="h6 fw-bold mb-3">Thông tin giao hàng</h2><p><strong>{{ $order->customer_name }}</strong><br><span class="muted">{{ $order->email }}<br>{{ $order->phone }}<br>{{ $order->address }}</span></p><div class="small p-3 rounded-3 bg-light mb-3"><strong>Thanh toán:</strong><br><span class="{{ in_array($order->payment_status, ['paid', 'refunded']) ? 'text-success' : 'muted' }} fw-bold">{{ $order->paymentStatusLabel() }}</span></div><div class="small p-3 rounded-3 bg-light mb-3"><strong>Vận đơn GHN:</strong><br>@if($order->ghn_order_code)<span class="text-success fw-bold">{{ $order->ghn_order_code }}</span><br><span class="muted">Trạng thái GHN: {{ $order->shippingStatusLabel() }}</span><form action="{{ route('admin.orders.sync-ghn', $order) }}" method="POST" class="mt-2">@csrf<button class="btn btn-sm btn-outline-secondary">Đồng bộ trạng thái GHN</button></form>@else<span class="muted">Chưa tạo. Chuyển trạng thái sang “Đã xác nhận” để gửi đơn lên GHN.</span>@endif</div>@if($order->note)<p class="small p-3 rounded-3 bg-light"><strong>Ghi chú:</strong> {{ $order->note }}</p>@endif<hr>@if($order->status === 'cancelled' && $order->payment_status === 'refund_pending')<form action="{{ route('admin.orders.confirm-refund', $order) }}" method="POST" class="mb-3" onsubmit="return confirm('Chỉ xác nhận sau khi tiền đã được hoàn thành công qua giao dịch gốc. Tiếp tục?')">@csrf<button class="btn btn-coral w-100">Xác nhận đã hoàn tiền</button></form>@endif @php($nextStatuses = app(\App\Services\OrderStateMachine::class)->allowedOrderTransitionsFor($order))@if($nextStatuses)<form action="{{ route('admin.orders.update', $order) }}" method="POST">@csrf @method('PATCH')<label class="form-label">Chuyển trạng thái đơn</label><select name="status" class="form-select">@foreach($nextStatuses as $key)<option value="{{ $key }}">{{ \App\Models\Order::STATUS_LABELS[$key] }}</option>@endforeach</select><button class="btn btn-ocean w-100 mt-3">Cập nhật trạng thái</button></form>@else<div class="alert alert-secondary mb-0">Đơn đang ở trạng thái cuối và không thể chuyển tiếp.</div>@endif</section></div>
</div>
@if($order->paymentTransactions->isNotEmpty())
<section class="panel p-4 mt-4">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><h2 class="h6 fw-bold mb-0">Lịch sử thanh toán</h2><span class="muted small">{{ $order->paymentTransactions->count() }} lần giao dịch</span></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Lần</th><th>Cổng</th><th>Trạng thái</th><th>Mã giao dịch</th><th>Phản hồi</th><th class="text-end">Số tiền</th><th>Thời gian</th></tr></thead>
            <tbody>
                @foreach($order->paymentTransactions->sortByDesc('id') as $transaction)
                <tr>
                    <td>#{{ $loop->remaining + 1 }}</td>
                    <td class="fw-bold">{{ strtoupper($transaction->gateway) }}</td>
                    <td><span class="badge {{ $transaction->status === 'paid' ? 'text-bg-success' : ($transaction->status === 'refund_pending' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ $transaction->statusLabel() }}</span></td>
                    <td>{{ $transaction->transaction_id ?: '—' }}</td>
                    <td><span class="d-block">{{ $transaction->message ?: '—' }}</span>@if(!is_null($transaction->result_code))<small class="muted">Mã: {{ $transaction->result_code }}</small>@endif</td>
                    <td class="text-end fw-bold">{{ number_format($transaction->amount, 0, ',', '.') }}đ</td>
                    <td>{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
