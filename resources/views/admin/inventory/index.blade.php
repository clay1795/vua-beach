@extends('layouts.admin')

@section('page-title', 'Lịch sử tồn kho')

@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
    <p class="muted mb-0">Theo dõi lượng hàng nhập, xuất và số dư của từng biến thể.</p>
    <div class="d-flex gap-2"><a class="btn btn-admin btn-sm" href="{{ route('admin.purchase-receipts.create') }}"><i class="bi bi-box-arrow-in-down me-1"></i>Nhập hàng</a><a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.products.index') }}"><i class="bi bi-box-seam me-1"></i>Quản lý sản phẩm</a></div>
</div>

<form method="GET" class="panel p-3 mb-3 row g-2">
    <div class="col-md-7"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Tìm tên sản phẩm hoặc SKU"></div>
    <div class="col-md-3"><select class="form-select" name="type"><option value="">Mọi biến động</option><option value="in" @selected(request('type') === 'in')>Nhập kho</option><option value="out" @selected(request('type') === 'out')>Xuất kho</option></select></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-ocean flex-grow-1">Lọc</button><a class="btn btn-light" href="{{ route('admin.inventory.index') }}">Xóa</a></div>
</form>

<section class="panel p-3 table-responsive">
    @if($movements->isEmpty())
        <div class="text-center py-5"><div class="admin-empty-icon"><i class="bi bi-clipboard-data"></i></div><h2 class="h5">Chưa có biến động kho</h2><p class="muted mb-0">Dữ liệu sẽ được thêm khi nhập tồn hoặc khi đơn hàng thay đổi.</p></div>
    @else
        <table class="table align-middle mb-0">
            <thead><tr><th>Thời gian</th><th>Sản phẩm</th><th>Phân loại / SKU</th><th>Biến động</th><th>Số dư</th><th>Lý do</th></tr></thead>
            <tbody>@foreach($movements as $movement)
                <tr>
                    <td><b>{{ $movement->created_at->format('d/m/Y') }}</b><br><small class="muted">{{ $movement->created_at->format('H:i') }}</small></td>
                    <td>{{ $movement->variant?->product?->name ?? 'Sản phẩm đã xóa' }}</td>
                    <td><small>{{ $movement->variant?->color }} · {{ $movement->variant?->size }}</small><br><code>{{ $movement->variant?->sku ?: 'Chưa có SKU' }}</code></td>
                    <td><span class="badge {{ $movement->type === 'in' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $movement->type === 'in' ? '+' : '-' }}{{ $movement->quantity }}</span></td>
                    <td><b>{{ $movement->balance_after }}</b></td>
                    <td><span>{{ ['initial_stock' => 'Tạo biến thể', 'manual_adjustment' => 'Điều chỉnh thủ công', 'purchase_receipt' => 'Phiếu nhập kho', 'order_placed' => 'Khách đặt đơn', 'order_cancelled' => 'Hủy đơn', 'order_restored' => 'Khôi phục đơn', 'return_received' => 'Nhận hàng hoàn', 'exchange_replacement_reserved' => 'Giữ hàng đổi size', 'exchange_reservation_released' => 'Nhả hàng đổi size'][$movement->reason] ?? $movement->reason }}</span>@if($movement->purchaseReceipt)<br><a class="small" href="{{ route('admin.purchase-receipts.show',$movement->purchaseReceipt) }}">{{ $movement->purchaseReceipt->receipt_code }}</a>@endif @if($movement->note)<br><small class="muted">{{ $movement->note }}</small>@endif</td>
                </tr>
            @endforeach</tbody>
        </table>
    @endif
</section>
<div class="mt-3">{{ $movements->links() }}</div>
@endsection
