@extends('layouts.app')

@section('title', $siteSettings->site_name)

@section('content')
<main>
    <section class="home-hero" aria-labelledby="home-hero-title">
        <div class="home-hero-media">
            <x-responsive-image :src="asset('images/vua-beach-hero-v2.webp')" :alt="'Bộ sưu tập đồ bơi '.$siteSettings->site_name.' bên bờ biển'" width="1800" height="1200" fetchpriority="high" />
        </div>
        <div class="home-hero-overlay"></div>
        <div class="container home-hero-content">
            <p class="hero-kicker">Bộ sưu tập hè 2026</p>
            <h1 id="home-hero-title">Mùa hè<br>thuộc về bạn.</h1>
            <p>Đồ bơi hiện đại, thoải mái và sẵn sàng cho mọi chuyến đi.</p>
            <a href="{{ route('products.index') }}" class="btn btn-ocean px-4">Khám phá ngay <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
    </section>

    <section class="home-service-bar" aria-label="Dịch vụ mua hàng">
        <div class="container">
            <div class="service-grid" tabindex="0" aria-label="Các chính sách dịch vụ, có thể cuộn ngang trên điện thoại">
                <div><i class="bi bi-truck"></i><span><strong>Giao hàng toàn quốc</strong><small>Kiểm tra trước khi nhận</small></span></div>
                <div><i class="bi bi-arrow-repeat"></i><span><strong>Đổi size trong 7 ngày</strong><small>Hỗ trợ chọn lại kích cỡ</small></span></div>
                <div><i class="bi bi-shield-check"></i><span><strong>Mua sắm an tâm</strong><small>Thông tin được bảo mật</small></span></div>
            </div>
        </div>
    </section>

    <section class="container home-section" aria-labelledby="category-title" data-reveal>
        <header class="home-section-heading text-center">
            <p class="section-kicker">Chọn cho từng thành viên</p>
            <h2 id="category-title">Đồ bơi cho cả gia đình</h2>
            <p>Thiết kế thoải mái cho nữ, nam và trẻ em, từ hồ bơi đến những ngày ngoài biển.</p>
        </header>

        <div class="home-category-grid">
            @forelse($categories as $category)
                <a class="home-category-card" href="{{ route('products.index', ['category' => $category->slug]) }}">
                    <x-responsive-image :src="$category->image_url ?: asset('images/vua-beach-hero-v2.webp')" :alt="$category->name" width="600" height="900" loading="lazy" decoding="async" />
                    <span class="home-category-shade"></span>
                    <span class="home-category-copy">
                        <strong>{{ mb_strtoupper($category->name) }}</strong>
                        <small>{{ $category->products_count }} sản phẩm</small>
                        <span>Xem ngay <i class="bi bi-arrow-right"></i></span>
                    </span>
                </a>
            @empty
                <div class="panel empty-state" style="grid-column:1/-1">
                    <div class="empty-icon"><i class="bi bi-grid"></i></div>
                    <h3 class="h5">Danh mục đang được cập nhật</h3>
                </div>
            @endforelse
        </div>
    </section>

    <section class="home-featured home-section" aria-labelledby="featured-title" data-reveal>
        <div class="container">
            <header class="home-section-heading text-center">
                <h2 id="featured-title">Sản phẩm nổi bật</h2>
                <p>Những thiết kế được chọn nhiều nhờ form dễ mặc, vận động thoải mái và màu sắc bền đẹp.</p>
            </header>

            <div class="featured-mosaic">
                @forelse($products as $product)
                    <article class="featured-tile {{ $loop->first ? 'featured-tile-large' : '' }}">
                        <a class="featured-image-link" href="{{ route('products.show', $product) }}">
                            <x-responsive-image :src="$product->image_url ?: asset('images/product-placeholder.svg')" :alt="$product->name" :fallback="asset('images/product-placeholder.svg')" width="600" height="900" loading="lazy" decoding="async" />
                            @if($product->sale_price)<span class="sale-badge">Giảm giá</span>@endif
                        </a>
                        <div class="featured-info">
                            <div class="featured-copy">
                                <small>{{ $product->category->name }}</small>
                                <h3>{{ $product->name }}</h3>
                                <div class="featured-price">
                                    <strong>{{ number_format($product->display_price, 0, ',', '.') }}đ</strong>
                                    @if($product->sale_price)<del>{{ number_format($product->price, 0, ',', '.') }}đ</del>@endif
                                </div>
                            </div>
                            @php($availableVariants = $product->variants->where('stock', '>', 0))
                            @if($availableVariants->isNotEmpty())
                                <form method="POST" action="{{ route('cart.add') }}" class="featured-cart-form">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1">
                                    <label class="visually-hidden" for="featured-variant-{{ $product->id }}">Chọn phân loại {{ $product->name }}</label>
                                    <select id="featured-variant-{{ $product->id }}" name="variant_id" class="form-select form-select-sm">
                                        @foreach($availableVariants as $variant)
                                            <option value="{{ $variant->id }}">{{ $variant->color }} - {{ $variant->size }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-ocean" type="submit" aria-label="{{ auth()->guest() ? 'Đăng nhập để mua' : (auth()->user()->hasVerifiedEmail() ? 'Thêm vào giỏ' : 'Xác thực để mua') }}">
                                        <i class="bi {{ auth()->guest() ? 'bi-person' : (auth()->user()->hasVerifiedEmail() ? 'bi-bag-plus' : 'bi-envelope-check') }}"></i>
                                        <span>{{ auth()->guest() ? 'Đăng nhập để mua' : (auth()->user()->hasVerifiedEmail() ? 'Thêm vào giỏ' : 'Xác thực để mua') }}</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="panel empty-state" style="grid-column:1/-1">
                        <div class="empty-icon"><i class="bi bi-stars"></i></div>
                        <h3 class="h5">Sản phẩm sắp có mặt</h3>
                    </div>
                @endforelse
            </div>

            <div class="text-center mt-5">
                <a class="btn btn-quiet px-4" href="{{ route('products.index') }}">Xem tất cả sản phẩm <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
    </section>

    <section class="home-story" data-reveal>
        <div class="container home-story-grid">
            <div class="home-story-media">
                <x-responsive-image :src="asset('images/vua-beach-story-v2.webp')" alt="Hai người mặc đồ bơi dài tay đi dọc bờ biển" loading="lazy" decoding="async" width="1536" height="1024" />
            </div>
            <div class="home-story-copy">
                <h2>Tự tin xuống nước, theo cách của bạn.</h2>
                <p>Vua Beach chọn form dáng dễ mặc, chất liệu co giãn và thiết kế phù hợp nhiều vóc dáng. Bạn thoải mái học bơi, đi biển hoặc vận động ngoài trời.</p>
                <div class="home-story-actions">
                    <a class="btn btn-ocean" href="{{ route('products.index') }}">Chọn đồ bơi <i class="bi bi-arrow-right ms-1"></i></a>
                    <a class="home-story-policy" href="{{ route('policies.returns') }}">Xem chính sách đổi size</a>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
