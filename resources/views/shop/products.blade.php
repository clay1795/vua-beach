@extends('layouts.app')

@section('title', 'Cửa hàng')

@section('content')
<main class="container section-space storefront-catalog">
    <header class="section-head catalog-heading">
        <p class="section-kicker">Bộ sưu tập Vua Beach</p>
        <h1 class="page-title mb-3">Đồ bơi cho mọi hành trình</h1>
        <p class="section-subtitle mb-0">Lọc nhanh theo nhu cầu, sau đó chọn màu và kích cỡ phù hợp với bạn.</p>
    </header>

    <details class="catalog-filter-disclosure" open data-active-filters="{{ request()->hasAny(['q', 'category', 'sort']) ? 'true' : 'false' }}">
        <summary>
            <span><i class="bi bi-sliders2" aria-hidden="true"></i> Lọc và sắp xếp</span>
            @if(request()->hasAny(['q', 'category', 'sort']))<small>Đang áp dụng</small>@endif
        </summary>
        <form class="panel filter-panel mb-5 row g-2 align-items-end" aria-label="Bộ lọc sản phẩm">
            <div class="col-lg-5 filter-field">
                <label class="form-label" for="catalog-query">Tìm kiếm</label>
                <div class="input-group">
                    <span class="input-group-text border-end-0"><i class="bi bi-search"></i></span>
                    <input id="catalog-query" name="q" value="{{ request('q') }}" class="form-control border-start-0 ps-0" placeholder="Nhập tên sản phẩm">
                </div>
            </div>
            <div class="col-md-5 col-lg-3 filter-field">
                <label class="form-label" for="catalog-category">Danh mục</label>
                <select id="catalog-category" name="category" class="form-select">
                    <option value="">Tất cả danh mục</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 col-lg-2 filter-field">
                <label class="form-label" for="catalog-sort">Sắp xếp</label>
                <select id="catalog-sort" name="sort" class="form-select">
                    <option value="">Mới cập nhật</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Giá thấp trước</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Giá cao trước</option>
                </select>
            </div>
            <div class="col-md-3 col-lg-2 filter-submit"><button class="btn btn-ocean w-100" type="submit"><i class="bi bi-sliders2 me-1"></i> Áp dụng</button></div>
        </form>
    </details>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h5 fw-bold mb-0">{{ $products->total() }} sản phẩm</h2>
        @if(request()->hasAny(['q', 'category', 'sort']))<a href="{{ route('products.index') }}" class="small fw-semibold">Xóa bộ lọc</a>@endif
    </div>

    <div class="row product-grid g-4" data-reveal>
        @forelse($products as $product)
            <div class="col-sm-6 col-lg-4">@include('shop.product-card')</div>
        @empty
            <div class="col-12">
                <div class="panel empty-state">
                    <div class="empty-icon"><i class="bi bi-search"></i></div>
                    <h2 class="h4">Không tìm thấy sản phẩm</h2>
                    <p class="text-secondary">Hãy thử từ khóa hoặc danh mục khác.</p>
                    <a class="btn btn-ocean" href="{{ route('products.index') }}">Xem tất cả</a>
                </div>
            </div>
        @endforelse
    </div>

    {{ $products->onEachSide(1)->links('pagination.storefront') }}
</main>
@endsection
