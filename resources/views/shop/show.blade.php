@extends('layouts.app')

@section('title', $product->name)
@section('meta_description', Str::limit(strip_tags($product->description ?: $product->name.' tại '.$siteSettings->site_name), 155))
@if($product->image_url)@section('og_image', $product->image_url)@endif

@section('content')
<main class="container section-space pt-4 product-detail-page">
    <a href="{{ route('products.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Quay lại cửa hàng</a>

    <div class="row g-4 g-lg-5 mt-2 align-items-center">
        <div class="col-lg-7 product-detail-media">
            @php
                $productImages = collect([$product->image_url])->filter()->merge($product->images->pluck('path'))->unique()->values();
            @endphp
            <x-responsive-image id="mainProductImage" class="product-detail-image" :src="$productImages->first() ?: asset('images/product-placeholder.svg')" :alt="$product->name" :fallback="asset('images/product-placeholder.svg')" width="1200" height="1400" fetchpriority="high" decoding="async" />
            @if($productImages->count() > 1)
                <div class="product-thumbnails" aria-label="Ảnh sản phẩm">
                    @foreach($productImages as $image)
                        <button type="button" class="product-thumbnail {{ $loop->first ? 'is-active' : '' }}" data-image="{{ $image }}"><x-responsive-image :src="$image" :alt="'Xem ảnh '.$loop->iteration.' của '.$product->name" :fallback="asset('images/product-placeholder.svg')" width="58" height="72" loading="lazy" decoding="async" /></button>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="col-lg-5 product-detail-copy" data-reveal>
            <span class="badge-soft">{{ $product->category->name }}</span>
            <h1 class="page-title mt-3 mb-3">{{ $product->name }}</h1>
            <div class="mb-4">
                <span class="price fs-3">{{ number_format($product->display_price, 0, ',', '.') }}đ</span>
                @if($product->sale_price)<span class="old-price fs-6">{{ number_format($product->price, 0, ',', '.') }}đ</span>@endif
            </div>
            <p class="product-description">{{ $product->description ?: 'Thiết kế dễ mặc, thoải mái khi vận động và phù hợp cho những ngày ở hồ bơi hoặc ngoài biển.' }}</p>

            @php
                $availableVariants = $product->variants->where('stock', '>', 0);
                $variantPickerData = $availableVariants->map(function ($variant) {
                    return ['id' => $variant->id, 'color' => $variant->color, 'size' => $variant->size, 'stock' => $variant->stock];
                })->values();
            @endphp
            <form action="{{ route('cart.add') }}" method="POST" class="panel p-4 mt-4 product-buy-box">
                @csrf
                @if($availableVariants->isNotEmpty())
                    <div class="variant-picker" data-variants='@json($variantPickerData)'>
                        <input type="hidden" id="selectedVariant" data-variant-field>
                        <div class="variant-picker-group">
                            <span class="form-label d-block">Màu sắc</span>
                            <div class="variant-choice-list" id="colorChoices" aria-label="Chọn màu sắc"></div>
                        </div>
                        <div class="variant-picker-group mb-0">
                            <span class="form-label d-block">Kích cỡ</span>
                            <div class="variant-choice-list" id="sizeChoices" aria-label="Chọn kích cỡ"></div>
                            <p id="variantStockNote" class="variant-stock-note mb-0" aria-live="polite"></p>
                        </div>
                    </div>
                    <noscript>
                        <label class="form-label mt-3" for="variant">Màu sắc và kích cỡ</label>
                        <select id="variant" name="variant_id" class="form-select" required>
                            @foreach($availableVariants as $variant)<option value="{{ $variant->id }}">{{ $variant->color }} - Size {{ $variant->size }} (Còn {{ $variant->stock }})</option>@endforeach
                        </select>
                    </noscript>
                @else
                    <p class="mb-3 text-secondary">Sản phẩm đang hết hàng.</p>
                @endif
                <div class="row g-2">
                    <div class="col-4 product-quantity-field">
                        <label class="form-label" for="quantity">Số lượng</label>
                        <input id="quantity" type="number" name="quantity" min="1" max="10" value="1" class="form-control">
                    </div>
                    <div class="col-8 product-add-field">
                        <button class="btn btn-ocean w-100" type="submit" @disabled($availableVariants->isEmpty())>
                            <i class="bi {{ auth()->guest() ? 'bi-person' : (auth()->user()->hasVerifiedEmail() ? 'bi-bag-plus' : 'bi-envelope-check') }} me-1"></i>
                            @if($availableVariants->isEmpty())
                                Đang hết hàng
                            @elseif(auth()->guest())
                                Đăng nhập để mua
                            @elseif(! auth()->user()->hasVerifiedEmail())
                                Xác thực email để mua
                            @else
                                Thêm vào giỏ
                            @endif
                        </button>
                    </div>
                </div>
            </form>
            <div class="product-assurance" aria-label="Quyền lợi mua hàng">
                <span><i class="bi bi-arrow-repeat"></i> Đổi size trong 7 ngày</span>
                <span><i class="bi bi-shield-check"></i> Thanh toán bảo mật</span>
                <span><i class="bi bi-truck"></i> Giao hàng toàn quốc</span>
            </div>
            @auth
                <form method="POST" action="{{ route('wishlist.toggle',$product) }}" class="mt-3">@csrf
                    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-heart me-1"></i>Thêm / bỏ yêu thích</button>
                </form>
            @endauth
        </div>
    </div>

    <section class="mt-5 pt-4">
        <div class="section-head"><div><p class="eyebrow">Cảm nhận khách hàng</p><h2 class="section-title mb-0">Đánh giá sản phẩm</h2></div><span class="muted">{{ $product->reviews->count() }} đánh giá</span></div>
        @auth
            @if($reviewableOrders->isNotEmpty())
                <form method="POST" action="{{ route('reviews.store', $product) }}" class="panel p-4 mt-4 mb-4">
                    @csrf
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-end">
                        <div class="flex-grow-1">
                            <label class="form-label" for="review-order">Đơn hàng đã mua</label>
                            <select class="form-select" id="review-order" name="order_id" required>
                                @foreach($reviewableOrders as $reviewOrder)
                                    <option value="{{ $reviewOrder->id }}">{{ $reviewOrder->order_code }} · Hoàn thành {{ optional($reviewOrder->completed_at)->format('d/m/Y') }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="review-rating">Đánh giá</label>
                            <select class="form-select" id="review-rating" name="rating" required>
                                @for($star = 5; $star >= 1; $star--)<option value="{{ $star }}">{{ $star }} sao</option>@endfor
                            </select>
                        </div>
                    </div>
                    <label class="form-label mt-3" for="review-content">Chia sẻ trải nghiệm <span class="text-secondary fw-normal">(không bắt buộc)</span></label>
                    <textarea class="form-control" id="review-content" name="content" rows="3" maxlength="1500" placeholder="Chất liệu, form dáng và trải nghiệm sử dụng của bạn..."></textarea>
                    <button class="btn btn-ocean mt-3" type="submit"><i class="bi bi-star me-1"></i> Gửi đánh giá</button>
                </form>
            @elseif(auth()->user()->hasVerifiedEmail())
                <p class="text-secondary mt-3">Bạn có thể đánh giá sau khi đơn hàng chứa sản phẩm này đã hoàn thành.</p>
            @endif
        @endauth
        <div class="row g-3">@forelse($product->reviews as $review)<div class="col-md-6"><article class="panel p-3"><div class="d-flex justify-content-between"><b>{{ $review->user->name }}</b><span class="text-warning">{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</span></div><p class="mb-0 mt-2 text-secondary">{{ $review->content ?: 'Khách hàng không để lại nội dung.' }}</p></article></div>@empty <p class="text-secondary">Chưa có đánh giá. Khách đã mua và nhận hàng có thể đánh giá sản phẩm tại đây.</p>@endforelse</div>
    </section>

    @if($related->isNotEmpty())
        <section class="mt-5 pt-5" data-reveal>
            <div class="section-head"><h2 class="section-title mb-0">Có thể bạn sẽ thích</h2></div>
            <div class="row product-grid g-4">
                @foreach($related as $product)<div class="col-sm-6 col-lg-4">@include('shop.product-card')</div>@endforeach
            </div>
        </section>
    @endif
</main>
<script>
document.querySelectorAll('.product-thumbnail').forEach(button => button.addEventListener('click', () => {
    const mainImage = document.getElementById('mainProductImage');
    const mainPicture = mainImage.closest('picture');
    const thumbnailPicture = button.querySelector('picture');
    mainImage.onerror = () => {
        mainImage.onerror = null;
        mainImage.src = '{{ asset('images/product-placeholder.svg') }}';
    };
    mainPicture.querySelectorAll('source').forEach(source => source.remove());
    thumbnailPicture.querySelectorAll('source').forEach(source => mainPicture.insertBefore(source.cloneNode(), mainImage));
    mainImage.src = button.dataset.image;
    document.querySelectorAll('.product-thumbnail').forEach(item => item.classList.toggle('is-active', item === button));
}));

(() => {
    const picker = document.querySelector('.variant-picker');
    if (!picker) return;
    const variants = JSON.parse(picker.dataset.variants);
    const colorChoices = document.getElementById('colorChoices');
    const sizeChoices = document.getElementById('sizeChoices');
    const selectedVariant = document.getElementById('selectedVariant');
    const stockNote = document.getElementById('variantStockNote');
    const quantity = document.getElementById('quantity');
    let color = variants[0].color;
    let variant = variants[0];
    selectedVariant.name = 'variant_id';

    const button = (label, selected, onClick) => {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = `variant-choice${selected ? ' is-selected' : ''}`;
        element.textContent = label;
        element.setAttribute('aria-pressed', selected ? 'true' : 'false');
        element.addEventListener('click', onClick);
        return element;
    };
    const renderSizes = () => {
        const colorVariants = variants.filter((item) => item.color === color);
        if (!colorVariants.some((item) => item.id === variant.id)) variant = colorVariants[0];
        sizeChoices.replaceChildren(...colorVariants.map((item) => button(`Size ${item.size}`, item.id === variant.id, () => {
            variant = item;
            renderSizes();
        })));
        selectedVariant.value = variant.id;
        quantity.max = Math.min(10, variant.stock);
        if (Number(quantity.value) > Number(quantity.max)) quantity.value = quantity.max;
        stockNote.textContent = `Còn ${variant.stock} sản phẩm cho màu ${variant.color}, size ${variant.size}.`;
    };
    const renderColors = () => {
        const colors = [...new Set(variants.map((item) => item.color))];
        colorChoices.replaceChildren(...colors.map((item) => button(item, item === color, () => {
            color = item;
            renderColors();
            renderSizes();
        })));
    };
    renderColors();
    renderSizes();
})();
</script>
@endsection
