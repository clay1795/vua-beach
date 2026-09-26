@extends('layouts.admin')
@section('page-title', 'Quản lý danh mục')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><p class="muted mb-0">Sắp xếp danh mục để khách hàng dễ tìm sản phẩm.</p><a class="btn btn-ocean" href="{{ route('admin.categories.create') }}"><i class="bi bi-plus-lg me-1"></i>Thêm danh mục</a></div>
<div class="panel p-3 table-responsive">
@if($categories->isEmpty())
    <div class="text-center py-5"><div class="admin-empty-icon"><i class="bi bi-collection"></i></div><h2 class="h5">Chưa có danh mục</h2><p class="muted">Hãy tạo danh mục đầu tiên trước khi thêm sản phẩm.</p><a class="btn btn-ocean" href="{{ route('admin.categories.create') }}">Thêm danh mục</a></div>
@else
    <table class="table align-middle mb-0"><thead><tr><th>ID</th><th>Tên danh mục</th><th>Sản phẩm</th><th class="text-end">Thao tác</th></tr></thead><tbody>@foreach($categories as $category)<tr><td>#{{ $category->id }}</td><td><b>{{ $category->name }}</b><br><small class="muted">{{ $category->description }}</small></td><td><span class="status status-confirmed">{{ $category->products_count }} sản phẩm</span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.categories.edit', $category) }}">Sửa</a><form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline">@csrf @method('DELETE')<button onclick="return confirm('Xóa danh mục này?')" class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr>@endforeach</tbody></table>
@endif
</div>
@endsection
