@extends('layouts.admin')
@section('page-title', 'Nhà cung cấp')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <p class="muted mb-0">Lưu thông tin nguồn hàng để lập phiếu nhập kho có thể đối soát.</p>
    <a class="btn btn-admin" href="{{ route('admin.suppliers.create') }}"><i class="bi bi-plus-lg me-1"></i>Thêm nhà cung cấp</a>
</div>
<form method="GET" class="panel p-3 mb-3 row g-2"><div class="col-md-10"><label class="visually-hidden" for="supplierSearch">Tìm nhà cung cấp</label><input id="supplierSearch" class="form-control" name="q" value="{{ request('q') }}" placeholder="Tìm theo tên, số điện thoại hoặc email"></div><div class="col-md-2 d-flex gap-2"><button class="btn btn-ocean flex-grow-1">Tìm</button><a class="btn btn-light" href="{{ route('admin.suppliers.index') }}">Xóa</a></div></form>
<section class="panel p-3 table-responsive">
    @if($suppliers->isEmpty())<div class="text-center py-5"><div class="admin-empty-icon"><i class="bi bi-truck"></i></div><h2 class="h5">Chưa có nhà cung cấp</h2><p class="muted">Thêm nguồn hàng trước khi lập phiếu nhập kho.</p></div>
    @else <table class="table align-middle mb-0"><thead><tr><th>Nhà cung cấp</th><th>Liên hệ</th><th>Địa chỉ</th><th>Phiếu nhập</th><th>Trạng thái</th><th></th></tr></thead><tbody>@foreach($suppliers as $supplier)<tr><td><b>{{ $supplier->name }}</b><br><small class="muted">{{ $supplier->contact_name ?: 'Chưa có người liên hệ' }}</small></td><td>{{ $supplier->phone ?: '—' }}<br><small class="muted">{{ $supplier->email ?: '' }}</small></td><td>{{ $supplier->address ?: '—' }}</td><td>{{ $supplier->purchase_receipts_count }}</td><td><span class="badge {{ $supplier->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $supplier->is_active ? 'Đang dùng' : 'Ngừng dùng' }}</span></td><td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('admin.suppliers.edit',$supplier) }}">Sửa</a>@if($supplier->is_active)<form class="d-inline" method="POST" action="{{ route('admin.suppliers.destroy',$supplier) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Ngừng sử dụng nhà cung cấp này?')">Ngừng dùng</button></form>@endif</td></tr>@endforeach</tbody></table>@endif
</section><div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
