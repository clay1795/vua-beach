@extends('layouts.admin')
@section('page-title', $category->exists ? 'Sửa danh mục' : 'Thêm danh mục')
@section('content')
<div class="admin-form-wrap">
    <p class="muted mb-4">Thông tin danh mục sẽ giúp khách hàng tìm đúng nhóm sản phẩm nhanh hơn.</p>
    <form class="panel p-4" method="POST" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
        @csrf @if($category->exists) @method('PUT') @endif
        <div class="mb-3"><label class="form-label" for="categoryName">Tên danh mục</label><input id="categoryName" name="name" class="form-control" value="{{ old('name', $category->name) }}" required></div>
        <div class="mb-3"><label class="form-label" for="categoryDescription">Mô tả</label><textarea id="categoryDescription" name="description" class="form-control" rows="4">{{ old('description', $category->description) }}</textarea></div>
        <div><label class="form-label" for="categoryImage">Ảnh danh mục</label><input id="categoryImage" type="file" name="category_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/avif"><div class="form-text">Tối đa 5 MB; hệ thống tự tối ưu và lưu ảnh tại máy chủ.</div>@if($category->image_url)<x-responsive-image :src="$category->image_url" :alt="'Ảnh hiện tại của '.$category->name" width="120" height="90" class="rounded object-fit-cover mt-2" loading="lazy" decoding="async" />@endif</div>
        <div class="admin-form-actions"><button class="btn btn-ocean">Lưu danh mục</button><a href="{{ route('admin.categories.index') }}" class="btn btn-light">Hủy</a></div>
    </form>
</div>
@endsection
