@extends('layouts.admin')
@section('page-title', 'Mã ưu đãi')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <p class="muted mb-0">Tạo ưu đãi theo phần trăm hoặc số tiền cố định cho khách hàng.</p>
    <a class="btn btn-ocean" href="{{ route('admin.coupons.create') }}"><i class="bi bi-plus-lg me-1"></i>Tạo mã ưu đãi</a>
</div>
<div class="panel p-3 table-responsive">
@if($coupons->isEmpty())
    <div class="text-center py-5"><div class="admin-empty-icon"><i class="bi bi-ticket-perforated"></i></div><h2 class="h5">Chưa có mã ưu đãi</h2><p class="muted mb-3">Tạo mã đầu tiên khi cửa hàng có chương trình khuyến mãi.</p><a class="btn btn-ocean" href="{{ route('admin.coupons.create') }}">Tạo mã ưu đãi</a></div>
@else
    <table class="table align-middle mb-0"><thead><tr><th>Mã</th><th>Ưu đãi</th><th>Điều kiện</th><th>Lượt dùng</th><th>Phạm vi</th><th>Thời hạn</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>
    @foreach($coupons as $coupon)
        <tr><td><b>{{ $coupon->code }}</b><br><small class="muted">{{ $coupon->name }}</small></td><td>{{ $coupon->type === 'percent' ? $coupon->value.'%' : number_format($coupon->value, 0, ',', '.').'đ' }}</td><td>Từ {{ number_format($coupon->min_order_amount, 0, ',', '.') }}đ</td><td>{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}<br><small class="muted">{{ $coupon->per_user_limit ? $coupon->per_user_limit.' lần/khách' : 'Không giới hạn/khách' }}</small></td><td>{{ ['all'=>'Toàn giỏ','products'=>'Sản phẩm chọn','categories'=>'Danh mục chọn'][$coupon->scope] }}</td><td><small>{{ $coupon->starts_at?->format('d/m/Y') ?? 'Ngay' }} - {{ $coupon->ends_at?->format('d/m/Y') ?? 'Không giới hạn' }}</small></td><td><span class="status status-{{ $coupon->is_active && (!$coupon->ends_at || $coupon->ends_at->isFuture()) ? 'confirmed' : 'cancelled' }}">{{ $coupon->is_active ? 'Đang bật' : 'Đã tắt' }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.coupons.edit', $coupon) }}">Sửa</a><form class="d-inline" method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Tắt mã ưu đãi này?')">Tắt</button></form></td></tr>
    @endforeach
    </tbody></table>
@endif
</div>
<div class="mt-3">{{ $coupons->links() }}</div>
@endsection
