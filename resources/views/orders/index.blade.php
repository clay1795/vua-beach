@extends('layouts.app')
@section('title', 'Đơn hàng của tôi')
@section('content')
<main class="container section-space commerce-page orders-page">
    <header class="commerce-page-head">
        <div><p class="eyebrow">Lịch sử mua hàng</p><h1>Đơn hàng của tôi</h1><p>Theo dõi trạng thái và xem lại thông tin các đơn hàng đã đặt.</p></div>
        <a href="{{ route('products.index') }}">Tiếp tục mua sắm <i class="bi bi-arrow-right"></i></a>
    </header>
    <div class="order-list">
        @forelse($orders as $order)
            <article class="order-card">
                <div class="order-card-main">
                    <div><span class="order-code-label">Mã đơn hàng</span><h2>{{ $order->order_code }}</h2><p>{{ $order->created_at->format('d/m/Y') }} lúc {{ $order->created_at->format('H:i') }}</p></div>
                    <span class="order-status status-{{ $order->status }}">{{ $order->statusLabel() }}</span>
                </div>
                <div class="order-preview-products" aria-label="Sản phẩm trong đơn">
                    @foreach($order->items->take(4) as $item)
                        @if($item->product_image_url || $item->product?->image_url)
                            <x-responsive-image :src="$item->product_image_url ?: $item->product?->image_url ?: asset('images/product-placeholder.svg')" :alt="$item->product_name" :fallback="asset('images/product-placeholder.svg')" :title="$item->product_name" width="42" height="42" loading="lazy" decoding="async" />
                        @else
                            <span class="order-preview-placeholder"><i class="bi bi-bag"></i></span>
                        @endif
                    @endforeach
                    @if($order->items->count() > 4)<span class="order-preview-more">+{{ $order->items->count() - 4 }}</span>@endif
                    <span class="order-preview-count">{{ $order->items->sum('quantity') }} sản phẩm</span>
                </div>
                <div class="order-card-total"><span>Tổng thanh toán</span><strong>{{ number_format($order->total_amount,0,',','.') }}đ</strong></div>
                <a class="order-card-link" href="{{ route('orders.show',$order) }}">Xem chi tiết <i class="bi bi-arrow-right"></i></a>
            </article>
        @empty
            <div class="order-empty"><span><i class="bi bi-receipt"></i></span><h2>Bạn chưa có đơn hàng nào</h2><p>Các đơn hàng sau khi đặt sẽ xuất hiện tại đây.</p><a class="btn btn-ocean" href="{{ route('products.index') }}">Khám phá sản phẩm</a></div>
        @endforelse
    </div>
</main>
@endsection
