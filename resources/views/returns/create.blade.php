@extends('layouts.app')
@section('title', 'Yêu cầu đổi trả')
@section('content')
<main class="container section-space commerce-page return-create-page">
    <a class="back-link" href="{{ route('orders.show', $order) }}"><i class="bi bi-arrow-left"></i> Quay lại đơn hàng</a>
    <header class="commerce-page-head"><div><p class="eyebrow">Đơn {{ $order->order_code }}</p><h1>Yêu cầu đổi trả</h1><p>Chọn sản phẩm cần xử lý. Vua Beach hỗ trợ trong 7 ngày sau khi đơn hoàn thành.</p></div></header>
    <form action="{{ route('returns.store', $order) }}" method="POST" class="row g-4" data-return-form>@csrf
        <div class="col-lg-8"><section class="panel p-4 p-md-5">
            <div class="flow-section-title"><span>1</span><div><h2>Chọn sản phẩm</h2><p>Chỉ chọn sản phẩm còn nguyên tem, chưa sử dụng.</p></div></div>
            <div class="return-item-list">
                @foreach($order->items as $index => $item)
                    @php
                        $sizes = $item->product?->variants?->where('is_active', true)->pluck('size')->unique()->values() ?? collect();
                        $returnableQuantity = (int) $item->returnable_quantity;
                    @endphp
                    <article class="return-item-card {{ $returnableQuantity === 0 ? 'opacity-50' : '' }}" data-return-item>
                        <label class="return-item-check"><input class="form-check-input return-item-toggle" type="checkbox" data-return-index="{{ $index }}" @disabled($returnableQuantity === 0)><span>{{ $returnableQuantity === 0 ? 'Sản phẩm này đã được xử lý hết' : 'Yêu cầu xử lý sản phẩm này' }}</span></label>
                        <div class="return-item-body">
                            @if($item->product_image_url || $item->product?->image_url)<x-responsive-image :src="$item->product_image_url ?: $item->product?->image_url" :alt="$item->product_name" :fallback="asset('images/product-placeholder.svg')" width="64" height="64" loading="lazy" decoding="async" />@endif
                            <div><strong>{{ $item->product_name }}</strong><p>{{ $item->color }} · Size {{ $item->size }} · Đã mua {{ $item->quantity }} sản phẩm · Còn {{ $returnableQuantity }} sản phẩm có thể đổi/trả</p></div>
                        </div>
                        <div class="row g-2 return-item-fields" data-return-fields="{{ $index }}">
                            <input type="hidden" name="items[{{ $index }}][order_item_id]" value="{{ $item->id }}" disabled>
                            <div class="col-md-5"><label class="form-label" for="returnQuantity{{ $index }}">Số lượng đổi/trả</label><input id="returnQuantity{{ $index }}" type="number" class="form-control" name="items[{{ $index }}][quantity]" min="1" max="{{ $returnableQuantity }}" value="1" disabled></div>
                            <div class="col-md-7 exchange-size-field"><label class="form-label" for="returnSize{{ $index }}">Size mong muốn</label><select id="returnSize{{ $index }}" class="form-select" name="items[{{ $index }}][desired_size]" disabled><option value="">Chọn size muốn đổi</option>@foreach($sizes as $size)<option value="{{ $size }}" @selected($size === $item->size)>{{ $size }}</option>@endforeach</select></div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="alert alert-danger mt-3 mb-0 d-none" role="alert" data-return-selection-error>
                Vui lòng tích chọn ít nhất một sản phẩm cần đổi/trả.
            </div>
            <hr class="my-4"><div class="flow-section-title"><span>2</span><div><h2>Thông tin yêu cầu</h2><p>Cửa hàng sẽ liên hệ theo email hoặc số điện thoại trong đơn.</p></div></div>
            <div class="vstack gap-3"><div><label class="form-label">Hình thức xử lý</label><div class="d-flex flex-wrap gap-3"><label class="payment-option flex-grow-1"><input class="form-check-input mt-0" type="radio" name="type" value="exchange" checked><span class="fs-4"><i class="bi bi-arrow-left-right"></i></span><span><b>Đổi size</b><br><small class="text-secondary">Đổi sang size phù hợp nếu còn hàng.</small></span></label><label class="payment-option flex-grow-1"><input class="form-check-input mt-0" type="radio" name="type" value="refund"><span class="fs-4"><i class="bi bi-cash-stack"></i></span><span><b>Hoàn hàng</b><br><small class="text-secondary">Cửa hàng kiểm tra và xử lý hoàn tiền.</small></span></label></div></div><div><label class="form-label" for="returnReason">Lý do</label><select id="returnReason" class="form-select" name="reason" required><option value="">Chọn lý do</option><option>Không vừa size</option><option>Sản phẩm bị lỗi</option><option>Không đúng mô tả</option><option>Khác</option></select></div><div><label class="form-label" for="returnNote">Ghi chú <span class="text-secondary fw-normal">(không bắt buộc)</span></label><textarea id="returnNote" class="form-control" name="customer_note" rows="3" placeholder="Mô tả thêm tình trạng sản phẩm hoặc yêu cầu của bạn"></textarea></div></div>
        </section></div>
        <div class="col-lg-4"><aside class="panel p-4 return-policy-note"><h2 class="h5">Lưu ý đổi trả</h2><ul><li>Gửi yêu cầu trong 7 ngày sau khi đơn hoàn thành.</li><li>Sản phẩm cần còn tem, chưa qua sử dụng hoặc giặt.</li><li>Size đổi phụ thuộc tồn kho thực tế tại thời điểm cửa hàng duyệt.</li><li>Chỉ khi cửa hàng nhận hàng hoàn, tồn kho mới được cập nhật.</li></ul><button class="btn btn-coral w-100 mt-2">Gửi yêu cầu đổi trả</button></aside></div>
    </form>
</main>
<script>
(() => {
    const typeInputs = document.querySelectorAll('input[name="type"]');
    const sync = () => {
        const exchange = document.querySelector('input[name="type"]:checked').value === 'exchange';
        document.querySelectorAll('.exchange-size-field').forEach(el => el.classList.toggle('d-none', !exchange));
    };
    document.querySelectorAll('.return-item-toggle').forEach(toggle => toggle.addEventListener('change', () => {
        const fields = document.querySelector(`[data-return-fields="${toggle.dataset.returnIndex}"]`);
        fields.querySelectorAll('input, select').forEach(input => input.disabled = !toggle.checked);
        fields.closest('[data-return-item]').classList.toggle('is-selected', toggle.checked);
    }));
    typeInputs.forEach(input => input.addEventListener('change', sync)); sync();
    const form = document.querySelector('[data-return-form]');
    const selectionError = document.querySelector('[data-return-selection-error]');
    form.addEventListener('submit', event => {
        const hasSelectedItem = [...document.querySelectorAll('.return-item-toggle')].some(toggle => toggle.checked);
        selectionError.classList.toggle('d-none', hasSelectedItem);
        if (!hasSelectedItem) {
            event.preventDefault();
            selectionError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();
</script>
@endsection
