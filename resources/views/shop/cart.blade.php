@extends('layouts.app')
@section('title', 'Giỏ hàng')
@section('content')
<main class="container section-space commerce-page cart-page">
    <header class="commerce-page-head">
        <div><p class="eyebrow">Giỏ hàng</p><h1>Các món bạn đã chọn</h1><p>Kiểm tra phân loại, số lượng và chọn sản phẩm cần thanh toán.</p></div>
        <a href="{{ route('products.index') }}">Tiếp tục mua sắm <i class="bi bi-arrow-right"></i></a>
    </header>

    @if($items->isEmpty())
        <div class="panel empty-state">
            <div class="empty-icon"><i class="bi bi-bag"></i></div>
            <h2 class="h4">Giỏ hàng đang trống</h2>
            <p class="text-secondary">Hãy khám phá các mẫu đồ bơi dành riêng cho chuyến đi của bạn.</p>
            <a class="btn btn-ocean" href="{{ route('products.index') }}">Xem sản phẩm</a>
        </div>
    @else
        <form id="checkout-selection-form" method="GET" action="{{ route('checkout.create') }}"></form>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="panel p-2 p-md-3 cart-list">
                    <div class="cart-select-all">
                        <label class="d-flex align-items-center gap-2 fw-semibold mb-0">
                            <input id="selectAll" class="form-check-input mt-0" type="checkbox" checked>
                            Chọn tất cả sản phẩm
                        </label>
                        <small class="text-secondary"><span id="selectedCount">{{ $items->count() }}</span>/{{ $items->count() }} sản phẩm</small>
                    </div>

                    @foreach($items as $item)
                        <article class="cart-line" data-unit-price="{{ $item['price'] }}">
                            <div class="cart-line-select">
                                <input class="form-check-input cart-item-check" type="checkbox" name="selected_variants[]" value="{{ $item['variant']->id }}" data-subtotal="{{ $item['subtotal'] }}" form="checkout-selection-form" aria-label="Chọn {{ $item['variant']->product->name }}" checked>
                            </div>
                            <div class="cart-line-media">
                                <x-responsive-image class="cart-line-image" :src="$item['variant']->product->image_url ?: asset('images/product-placeholder.svg')" :alt="$item['variant']->product->name" :fallback="asset('images/product-placeholder.svg')" width="92" height="112" loading="lazy" decoding="async" />
                            </div>
                            <div class="cart-line-info">
                                <a class="fw-bold text-decoration-none d-block mb-1" href="{{ route('products.show', $item['variant']->product) }}">{{ $item['variant']->product->name }}</a>
                                <small class="text-secondary">{{ $item['variant']->color }} · Size {{ $item['variant']->size }}</small>
                            </div>
                            <div class="cart-line-quantity">
                                <form action="{{ route('cart.update', $item['variant']->id) }}" method="POST" class="cart-quantity-form" data-quantity-form>
                                    @csrf @method('PATCH')
                                    <button class="btn btn-quiet btn-sm" type="button" data-quantity-step="-1" aria-label="Giảm số lượng"><i class="bi bi-dash-lg"></i></button>
                                    <input aria-label="Số lượng" class="form-control form-control-sm" type="number" name="quantity" min="1" max="10" value="{{ $item['quantity'] }}" data-quantity-input>
                                    <button class="btn btn-quiet btn-sm" type="button" data-quantity-step="1" aria-label="Tăng số lượng"><i class="bi bi-plus-lg"></i></button>
                                </form>
                            </div>
                            <div class="cart-line-actions text-end">
                                <strong class="price" data-line-subtotal>{{ number_format($item['subtotal'], 0, ',', '.') }}đ</strong>
                                <form action="{{ route('cart.remove', $item['variant']->id) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-link btn-sm text-danger p-0 mt-1">Xóa</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="col-lg-4">
                <aside class="panel p-4 order-summary checkout-summary">
                    <h2 class="h5 fw-bold mb-4">Sản phẩm thanh toán</h2>
                    <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Tạm tính</span><strong id="selectedSubtotal">{{ number_format($total, 0, ',', '.') }}đ</strong></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Giao hàng</span><span class="text-success fw-semibold">Miễn phí</span></div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center"><strong>Tổng cộng</strong><strong id="selectedTotal" class="price fs-5">{{ number_format($total, 0, ',', '.') }}đ</strong></div>
                    <button id="checkoutButton" form="checkout-selection-form" class="btn btn-coral w-100 mt-4">Thanh toán sản phẩm đã chọn <i class="bi bi-arrow-right ms-1"></i></button>
                    <p id="selectionWarning" class="small text-danger text-center mt-2 mb-0 d-none">Hãy chọn ít nhất một sản phẩm.</p>
                </aside>
            </div>
        </div>
    @endif
</main>

@if($items->isNotEmpty())
<script>
    const itemChecks = [...document.querySelectorAll('.cart-item-check')];
    const selectAll = document.getElementById('selectAll');
    const money = value => new Intl.NumberFormat('vi-VN').format(value) + 'đ';

    function updateSelection() {
        const selected = itemChecks.filter(input => input.checked);
        const total = selected.reduce((sum, input) => sum + Number(input.dataset.subtotal), 0);
        document.getElementById('selectedCount').textContent = selected.length;
        document.getElementById('selectedSubtotal').textContent = money(total);
        document.getElementById('selectedTotal').textContent = money(total);
        document.getElementById('checkoutButton').disabled = selected.length === 0;
        document.getElementById('selectionWarning').classList.toggle('d-none', selected.length > 0);
        selectAll.checked = selected.length === itemChecks.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < itemChecks.length;
    }

    selectAll.addEventListener('change', () => {
        itemChecks.forEach(input => input.checked = selectAll.checked);
        updateSelection();
    });
    itemChecks.forEach(input => input.addEventListener('change', updateSelection));

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    async function syncQuantity(form, nextQuantity) {
        const line = form.closest('.cart-line');
        const input = form.querySelector('[data-quantity-input]');
        const check = line.querySelector('.cart-item-check');
        const previous = Number(input.value);
        nextQuantity = Math.max(1, Math.min(10, Number(nextQuantity) || 1));
        input.value = nextQuantity;
        input.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'PATCH', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify({ quantity: nextQuantity }) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Không thể cập nhật số lượng.');
            check.dataset.subtotal = data.subtotal;
            line.querySelector('[data-line-subtotal]').textContent = money(data.subtotal);
            document.querySelector('.cart-count').textContent = data.cart_count;
            updateSelection();
        } catch (error) {
            input.value = previous;
            window.alert(error.message || 'Không thể cập nhật số lượng.');
        } finally { input.disabled = false; }
    }
    document.querySelectorAll('[data-quantity-form]').forEach(form => {
        const input = form.querySelector('[data-quantity-input]');
        let timer;
        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => syncQuantity(form, input.value), 450); });
        form.querySelectorAll('[data-quantity-step]').forEach(button => button.addEventListener('click', () => syncQuantity(form, Number(input.value) + Number(button.dataset.quantityStep))));
    });
</script>
@endif
@endsection
