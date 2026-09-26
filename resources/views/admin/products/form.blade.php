@extends('layouts.admin')
@section('page-title', $product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm')
@section('content')
<div class="admin-form-wrap">
    <p class="muted mb-4">Cập nhật thông tin bán hàng, hình ảnh và số lượng tồn kho theo từng phân loại.</p>
    <form class="panel p-4" method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
        @csrf @if($product->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Tên sản phẩm</label><input name="name" class="form-control" value="{{ old('name', $product->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Danh mục</label><select name="category_id" class="form-select" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Giá bán</label><input type="number" name="price" class="form-control" value="{{ old('price', $product->price) }}" required></div>
            <div class="col-md-4"><label class="form-label">Giá khuyến mãi</label><input type="number" name="sale_price" class="form-control" value="{{ old('sale_price', $product->sale_price) }}"></div>
            <div class="col-md-4"><label class="form-label">Trạng thái</label><select name="status" class="form-select"><option value="active" @selected(old('status', $product->status) === 'active')>Đang bán</option><option value="inactive" @selected(old('status', $product->status) === 'inactive')>Ngừng bán</option></select></div>
            <div class="col-12"><label class="form-label" for="coverImage">Ảnh đại diện</label><input id="coverImage" type="file" name="cover_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/avif"><div class="form-text">Tải ảnh từ máy, tối đa 5 MB. Hệ thống tự thu nhỏ và chuyển WebP để tải nhanh.</div>@if($product->image_url)<x-responsive-image :src="$product->image_url" :alt="'Ảnh đại diện hiện tại của '.$product->name" width="96" height="112" class="rounded object-fit-cover mt-2" loading="lazy" decoding="async" />@endif</div>
            <div class="col-12"><label class="form-label">Ảnh thư viện</label><input type="file" name="gallery_images[]" class="form-control" accept="image/jpeg,image/png,image/webp,image/avif" multiple><div class="form-text">Có thể chọn tối đa 8 ảnh bổ sung.</div>@if($product->images->isNotEmpty())<div class="d-flex flex-wrap gap-2 mt-2">@foreach($product->images as $image)<div class="position-relative"><x-responsive-image :src="$image->path" alt="Ảnh sản phẩm" width="64" height="64" class="rounded object-fit-cover" loading="lazy" decoding="async" /><button type="submit" form="delete-product-image-{{ $image->id }}" class="btn btn-sm btn-danger rounded-circle p-0 position-absolute top-0 start-100 translate-middle" style="width:22px;height:22px" onclick="return confirm('Xóa ảnh này?')" aria-label="Xóa ảnh"><i class="bi bi-x"></i></button></div>@endforeach</div>@endif</div>
            <div class="col-12"><label class="form-label">Mô tả sản phẩm</label><textarea name="description" class="form-control" rows="5" required>{{ old('description', $product->description) }}</textarea></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="featured" @checked(old('is_featured', $product->is_featured))><label class="form-check-label" for="featured">Hiển thị trong nhóm sản phẩm nổi bật</label></div></div>
        </div>
        <hr class="my-4">
        <section class="variant-editor" aria-labelledby="variant-editor-title">
            <div class="variant-editor-head">
                <div>
                    <h2 id="variant-editor-title" class="h6 fw-bold mb-1">Màu sắc, kích cỡ và tồn kho</h2>
                    <p class="muted small mb-0">Mỗi màu có thể có nhiều size. Tồn kho được nhập riêng cho từng tổ hợp màu và size.</p>
                </div>
                <div id="variantSummary" class="variant-summary" aria-live="polite"></div>
            </div>
            <div id="variantGroups" class="variant-groups">
                @php
                    $rawVariants = old('variants', $product->variants->isNotEmpty() ? $product->variants->toArray() : []);
                    $groupedVariants = collect($rawVariants)
                        ->filter(fn ($variant) => filled($variant['color'] ?? null) || filled($variant['size'] ?? null) || filled($variant['sku'] ?? null))
                        ->groupBy(fn ($variant) => $variant['color'] ?? '');
                    if ($groupedVariants->isEmpty()) {
                        $groupedVariants = collect(['' => [['color' => '', 'size' => '', 'sku' => '', 'stock' => 0, 'low_stock_threshold' => 5]]]);
                    }
                    $variantIndex = 0;
                @endphp
                @foreach($groupedVariants as $color => $colorVariants)
                    <article class="variant-color-group" data-color-group>
                        <div class="variant-color-head">
                            <div class="variant-color-input-wrap">
                                <label class="form-label mb-1">Màu sắc</label>
                                <input type="text" class="form-control" data-color-input value="{{ $color }}" placeholder="Ví dụ: Xanh navy" maxlength="50">
                            </div>
                            <button class="btn btn-outline-danger btn-sm" type="button" data-action="remove-color"><i class="bi bi-trash3 me-1"></i>Xóa màu</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table variant-size-table mb-0">
                                <thead><tr><th>Size</th><th>SKU nội bộ</th><th>Tồn kho</th><th>Cảnh báo thấp</th><th><span class="visually-hidden">Thao tác</span></th></tr></thead>
                                <tbody data-size-list>
                                @foreach($colorVariants as $variant)
                                    @php($currentIndex = $variantIndex++)
                                    <tr data-size-row>
                                        <td>
                                            <input type="hidden" name="variants[{{ $currentIndex }}][id]" value="{{ $variant['id'] ?? '' }}">
                                            <input type="hidden" name="variants[{{ $currentIndex }}][color]" value="{{ $color }}" data-color-field>
                                            <input class="form-control" name="variants[{{ $currentIndex }}][size]" placeholder="S, M, L..." maxlength="10" value="{{ $variant['size'] ?? '' }}" required>
                                        </td>
                                        <td><input class="form-control" name="variants[{{ $currentIndex }}][sku]" placeholder="Để trống để tự tạo" maxlength="80" value="{{ $variant['sku'] ?? '' }}"><div class="form-text">Mã tự sinh: VB-{{ $product->id ?: 'ID' }}-MÀU-SIZE</div></td>
                                        <td><input class="form-control" type="number" min="0" max="1000000" name="variants[{{ $currentIndex }}][stock]" value="{{ $variant['stock'] ?? 0 }}" required></td>
                                        <td><input class="form-control" type="number" min="0" max="1000000" name="variants[{{ $currentIndex }}][low_stock_threshold]" value="{{ $variant['low_stock_threshold'] ?? 5 }}" required></td>
                                        <td class="text-end"><button class="btn btn-light btn-sm variant-remove-size" type="button" data-action="remove-size" aria-label="Xóa size"><i class="bi bi-x-lg"></i></button></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button class="btn btn-outline-primary btn-sm mt-3" type="button" data-action="add-size"><i class="bi bi-plus-lg me-1"></i>Thêm size cho màu này</button>
                    </article>
                @endforeach
            </div>
            <button id="addColorButton" class="btn btn-outline-primary mt-3" type="button"><i class="bi bi-palette me-1"></i>Thêm màu mới</button>
        </section>
        <div class="admin-form-actions"><button class="btn btn-ocean">Lưu sản phẩm</button><a href="{{ route('admin.products.index') }}" class="btn btn-light">Hủy</a></div>
    </form>
</div>
@foreach($product->images as $image)
<form id="delete-product-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" class="d-none">@csrf @method('DELETE')</form>
@endforeach
<template id="variantColorTemplate">
    <article class="variant-color-group" data-color-group>
        <div class="variant-color-head">
            <div class="variant-color-input-wrap"><label class="form-label mb-1">Màu sắc</label><input type="text" class="form-control" data-color-input placeholder="Ví dụ: Đen" maxlength="50"></div>
            <button class="btn btn-outline-danger btn-sm" type="button" data-action="remove-color"><i class="bi bi-trash3 me-1"></i>Xóa màu</button>
        </div>
        <div class="table-responsive"><table class="table variant-size-table mb-0"><thead><tr><th>Size</th><th>SKU nội bộ</th><th>Tồn kho</th><th>Cảnh báo thấp</th><th><span class="visually-hidden">Thao tác</span></th></tr></thead><tbody data-size-list></tbody></table></div>
        <button class="btn btn-outline-primary btn-sm mt-3" type="button" data-action="add-size"><i class="bi bi-plus-lg me-1"></i>Thêm size cho màu này</button>
    </article>
</template>
<template id="variantSizeTemplate">
    <tr data-size-row>
        <td><input type="hidden" name="variants[__INDEX__][id]" value=""><input type="hidden" name="variants[__INDEX__][color]" value="" data-color-field><input class="form-control" name="variants[__INDEX__][size]" placeholder="S, M, L..." maxlength="10" required></td>
        <td><input class="form-control" name="variants[__INDEX__][sku]" placeholder="Để trống để tự tạo" maxlength="80"><div class="form-text">Mã tự sinh theo mẫu-màu-size</div></td>
        <td><input class="form-control" type="number" min="0" max="1000000" name="variants[__INDEX__][stock]" value="0" required></td>
        <td><input class="form-control" type="number" min="0" max="1000000" name="variants[__INDEX__][low_stock_threshold]" value="5" required></td>
        <td class="text-end"><button class="btn btn-light btn-sm variant-remove-size" type="button" data-action="remove-size" aria-label="Xóa size"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
@push('scripts')
<script>
(() => {
    const groups = document.getElementById('variantGroups');
    const colorTemplate = document.getElementById('variantColorTemplate');
    const sizeTemplate = document.getElementById('variantSizeTemplate');
    const summary = document.getElementById('variantSummary');
    let nextIndex = {{ $variantIndex }};

    const syncColor = (group) => {
        const color = group.querySelector('[data-color-input]').value.trim();
        group.querySelectorAll('[data-color-field]').forEach((field) => { field.value = color; });
    };
    const updateSummary = () => {
        const colorCount = groups.querySelectorAll('[data-color-group]').length;
        const rows = [...groups.querySelectorAll('[data-size-row]')];
        const stock = rows.reduce((total, row) => total + (Number(row.querySelector('[name$="[stock]"]').value) || 0), 0);
        summary.textContent = `${colorCount} màu · ${rows.length} biến thể · ${stock} sản phẩm tồn`;
    };
    const addSize = (group) => {
        const html = sizeTemplate.innerHTML.replaceAll('__INDEX__', nextIndex++);
        group.querySelector('[data-size-list]').insertAdjacentHTML('beforeend', html);
        syncColor(group);
        updateSummary();
        group.querySelector('[data-size-list] tr:last-child input:not([type="hidden"])')?.focus();
    };
    const addColor = () => {
        groups.insertAdjacentHTML('beforeend', colorTemplate.innerHTML);
        const group = groups.querySelector('[data-color-group]:last-child');
        addSize(group);
        group.querySelector('[data-color-input]').focus();
        updateSummary();
    };

    document.getElementById('addColorButton').addEventListener('click', addColor);
    groups.addEventListener('input', (event) => {
        const group = event.target.closest('[data-color-group]');
        if (!group) return;
        if (event.target.matches('[data-color-input]')) syncColor(group);
        if (event.target.matches('[name$="[stock]"]')) updateSummary();
    });
    groups.addEventListener('click', (event) => {
        const action = event.target.closest('[data-action]')?.dataset.action;
        const group = event.target.closest('[data-color-group]');
        if (!action || !group) return;
        if (action === 'add-size') addSize(group);
        if (action === 'remove-size') {
            event.target.closest('[data-size-row]').remove();
            if (!group.querySelector('[data-size-row]')) group.remove();
            updateSummary();
        }
        if (action === 'remove-color') {
            group.remove();
            updateSummary();
        }
    });
    groups.querySelectorAll('[data-color-group]').forEach(syncColor);
    updateSummary();
})();
</script>
@endpush
@endsection
