<article class="product-card">
    <a class="product-image-wrap" href="{{ route('products.show', $product) }}" aria-label="Xem {{ $product->name }}">
        @if($product->sale_price)<span class="sale-badge">Đang giảm giá</span>@endif
        <x-responsive-image class="product-image" :src="$product->image_url ?: asset('images/product-placeholder.svg')" :alt="$product->name" :fallback="asset('images/product-placeholder.svg')" loading="lazy" decoding="async" width="900" height="1100" />
    </a>
    <div class="product-content">
        <div class="product-meta">
            <div>
                <p class="product-category mb-1">{{ $product->category->name }}</p>
                <a class="product-name text-decoration-none d-block" href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
            </div>
            <div class="text-end">
                <span class="price">{{ number_format($product->display_price, 0, ',', '.') }}đ</span>
                @if($product->sale_price)<span class="old-price d-block">{{ number_format($product->price, 0, ',', '.') }}đ</span>@endif
            </div>
        </div>

        @php($availableVariants = $product->variants->where('stock', '>', 0))
        @if($availableVariants->isNotEmpty())
            <form action="{{ route('cart.add') }}" method="POST" class="quick-buy">
                @csrf
                <input type="hidden" name="quantity" value="1">
                <select name="variant_id" class="form-select" aria-label="Chọn phân loại {{ $product->name }}">
                    @foreach($availableVariants as $variant)
                        <option value="{{ $variant->id }}">{{ $variant->color }} - Size {{ $variant->size }}</option>
                    @endforeach
                </select>
                <button class="btn btn-ocean" type="submit" aria-label="{{ auth()->guest() ? 'Đăng nhập để thêm '.$product->name : (auth()->user()->hasVerifiedEmail() ? 'Thêm '.$product->name.' vào giỏ' : 'Xác thực email để thêm '.$product->name) }}">
                    <i class="bi {{ auth()->guest() ? 'bi-person' : (auth()->user()->hasVerifiedEmail() ? 'bi-bag-plus' : 'bi-envelope-check') }} me-1"></i>
                    {{ auth()->guest() ? 'Đăng nhập để mua' : (auth()->user()->hasVerifiedEmail() ? 'Thêm vào giỏ' : 'Xác thực để mua') }}
                </button>
            </form>
        @else
            <button class="btn btn-quiet w-100 mt-3" disabled>Đang hết hàng</button>
        @endif
    </div>
</article>
