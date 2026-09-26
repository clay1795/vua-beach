@extends('layouts.app')
@section('title', 'Đơn '.$order->order_code)
@section('content')
<main class="container section-space commerce-page order-detail-page">
    <a class="back-link" href="{{ route('orders.index') }}"><i class="bi bi-arrow-left"></i> Đơn hàng của tôi</a>
    <header class="order-detail-head">
        <div><p class="eyebrow">Chi tiết đơn hàng</p><h1>{{ $order->order_code }}</h1><p>Đặt ngày {{ $order->created_at->format('d/m/Y') }} lúc {{ $order->created_at->format('H:i') }}</p></div>
        <span class="order-status status-{{ $order->status }}">{{ $order->statusLabel() }}</span>
    </header>
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="panel order-items-panel">
                <div class="order-panel-head"><h2>Sản phẩm</h2><span>{{ $order->items->sum('quantity') }} sản phẩm</span></div>
                @foreach($order->items as $item)
                    <div class="order-item-row">
                        <div class="d-flex align-items-center gap-3">
                            @if($item->product_image_url || $item->product?->image_url)
                                <x-responsive-image class="order-item-image" :src="$item->product_image_url ?: $item->product?->image_url ?: asset('images/product-placeholder.svg')" :alt="$item->product_name" :fallback="asset('images/product-placeholder.svg')" width="64" height="64" loading="lazy" decoding="async" />
                            @endif
                            <div><strong>{{ $item->product_name }}</strong><p>{{ $item->color }} - Size {{ $item->size }} - Số lượng {{ $item->quantity }}</p></div>
                        </div>
                        <strong>{{ number_format($item->subtotal,0,',','.') }}đ</strong>
                    </div>
                @endforeach
                <div class="order-grand-total"><div><span>Tiền hàng</span><strong>{{ number_format($order->subtotal_amount ?: ($order->total_amount - $order->shipping_fee),0,',','.') }}đ</strong></div>@if($order->discount_amount)<div><span>Ưu đãi {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</span><strong class="text-success">-{{ number_format($order->discount_amount,0,',','.') }}đ</strong></div>@endif<div><span>Phí giao hàng</span><strong>{{ number_format($order->shipping_fee,0,',','.') }}đ</strong></div><div><span>Tổng thanh toán</span><strong>{{ number_format($order->total_amount,0,',','.') }}đ</strong></div></div>
            </section>
        </div>
        <div class="col-lg-4">
            <aside class="panel delivery-panel">
                <div class="order-panel-head"><h2>Thông tin nhận hàng</h2><i class="bi bi-geo-alt"></i></div>
                <div class="delivery-contact"><strong>{{ $order->customer_name }}</strong><span>{{ $order->phone }}</span><p>{{ $order->address }}</p></div>
                <div class="delivery-meta"><div><span>Thanh toán</span><strong>{{ ['cod' => 'Thanh toán trực tiếp', 'momo' => 'Ví MoMo', 'vnpay' => 'VNPAY-QR / Ngân hàng'][$order->payment_method] ?? $order->payment_method }}</strong></div><div><span>Trạng thái thanh toán</span><strong class="{{ $order->payment_status === 'paid' ? 'text-success' : '' }}">{{ $order->paymentStatusLabel() }}</strong></div>@if($order->vnpay_transaction_no)<div><span>Mã giao dịch VNPAY</span><strong>{{ $order->vnpay_transaction_no }}</strong></div>@endif<div><span>Vận chuyển</span><strong>{{ $order->shippingStatusLabel() }}</strong></div>@if($order->ghn_order_code)<div><span>Mã vận đơn GHN</span><strong class="text-success">{{ $order->ghn_order_code }}</strong></div>@endif @if($order->note)<div><span>Ghi chú</span><strong>{{ $order->note }}</strong></div>@endif</div>
            </aside>
            @if($order->status === 'pending' && $order->payment_method === 'cod' && $order->payment_status !== 'paid' && ! $order->ghn_order_code)
                <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-3" onsubmit="return confirm('Bạn muốn hủy đơn hàng này? Tồn kho sẽ được hoàn lại.')">@csrf<button class="btn btn-outline-danger w-100">Hủy đơn hàng</button></form>
            @endif
            @if($order->status === 'pending' && $order->payment_method === 'momo' && $order->payment_status === 'failed')
                <form method="POST" action="{{ route('orders.momo.retry', $order) }}" class="mt-3" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Đang kết nối MoMo…';">
                    @csrf
                    <button class="btn btn-primary w-100"><i class="bi bi-arrow-repeat me-1"></i> Thanh toán lại bằng MoMo</button>
                </form>
                <p class="small text-secondary mt-2 mb-0">Hệ thống giữ nguyên mã đơn và tạo một lần giao dịch mới.</p>
            @endif
            @php
                $activeReturn = $order->returnRequests->first(fn ($return) => in_array($return->status, ['requested', 'approved', 'received', 'completed'], true));
            @endphp
            @if($activeReturn)
                <div class="small p-3 rounded-3 bg-light mt-3"><strong>Yêu cầu đổi trả #{{ $activeReturn->id }}</strong><br><span class="muted">{{ ['requested'=>'Đang chờ cửa hàng duyệt','approved'=>'Đã được duyệt','received'=>'Cửa hàng đã nhận hàng hoàn','completed'=>'Đã hoàn tất'][$activeReturn->status] }}</span></div>
            @elseif($order->status === 'completed' && $order->completed_at && $order->completed_at->gte(now()->subDays(7)))
                <a class="btn btn-outline-secondary w-100 mt-3" href="{{ route('returns.create', $order) }}">Yêu cầu đổi trả / đổi size</a>
            @endif
        </div>
    </div>
    @if($order->paymentTransactions->isNotEmpty())
        <section class="panel order-timeline mt-4">
            <div class="order-panel-head"><h2>Lịch sử thanh toán</h2><i class="bi bi-credit-card"></i></div>
            <ol>
                @foreach($order->paymentTransactions->sortByDesc('id') as $transaction)
                    <li>
                        <span class="order-timeline-dot"></span>
                        <div>
                            <strong>Lần #{{ $loop->remaining + 1 }} · {{ strtoupper($transaction->gateway) }} · {{ $transaction->statusLabel() }}</strong>
                            <p>
                                {{ number_format($transaction->amount, 0, ',', '.') }}đ · {{ $transaction->created_at->format('d/m/Y H:i') }}
                                @if($transaction->transaction_id) · Mã GD: {{ $transaction->transaction_id }} @endif
                                @if($transaction->message) · {{ $transaction->message }} @endif
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
    <section class="panel order-timeline mt-4"><div class="order-panel-head"><h2>Lịch sử đơn hàng</h2><i class="bi bi-clock-history"></i></div><ol>@forelse($order->statusHistories as $history)<li><span class="order-timeline-dot"></span><div><strong>{{ \App\Models\Order::STATUS_LABELS[$history->status] ?? 'Đang cập nhật' }}</strong><p>{{ $history->note }} · {{ $history->created_at->format('d/m/Y H:i') }}</p></div></li>@empty<li><span class="order-timeline-dot"></span><div><strong>{{ $order->statusLabel() }}</strong><p>{{ $order->updated_at->format('d/m/Y H:i') }}</p></div></li>@endforelse</ol></section>
</main>
@endsection
