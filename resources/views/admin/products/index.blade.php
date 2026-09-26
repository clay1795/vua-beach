@extends('layouts.admin')
@section('page-title', 'Quản lý sản phẩm')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><p class="muted mb-0">Thêm, chỉnh sửa và theo dõi các sản phẩm đang bán.</p><a class="btn btn-ocean" href="{{ route('admin.products.create') }}"><i class="bi bi-plus-lg me-1"></i>Thêm sản phẩm</a></div>
<div class="panel p-3 table-responsive">
@if($products->isEmpty())
    <div class="text-center py-5"><div class="admin-empty-icon"><i class="bi bi-bag"></i></div><h2 class="h5">Chưa có sản phẩm</h2><p class="muted mb-3">Bắt đầu bằng cách thêm sản phẩm đầu tiên cho cửa hàng.</p><a class="btn btn-ocean" href="{{ route('admin.products.create') }}">Thêm sản phẩm</a></div>
@else
    <table class="table align-middle mb-0"><thead><tr><th>Ảnh</th><th>Sản phẩm</th><th>Danh mục</th><th>Giá</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>@foreach($products as $product)<tr><td><x-responsive-image :src="$product->image_url ?: asset('images/product-placeholder.svg')" :alt="$product->name" :fallback="asset('images/product-placeholder.svg')" width="55" height="55" class="rounded-3 object-fit-cover" loading="lazy" decoding="async" /></td><td><b>{{ $product->name }}</b></td><td>{{ $product->category->name }}</td><td>{{ number_format($product->display_price, 0, ',', '.') }}đ</td><td><span class="status status-{{ $product->status === 'active' ? 'confirmed' : 'cancelled' }}">{{ $product->status === 'active' ? 'Đang bán' : 'Ngừng bán' }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.products.edit', $product) }}">Sửa</a><form class="d-inline" action="{{ route('admin.products.destroy', $product) }}" method="POST">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa hoặc ngừng bán sản phẩm này?')">Xóa</button></form></td></tr>@endforeach</tbody></table>
@endif
</div><div class="mt-3">{{ $products->links() }}</div>
@endsection
