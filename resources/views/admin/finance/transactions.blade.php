@extends('layouts.admin')
@section('page-title','Giao dịch thanh toán')
@section('content')
@include('admin.finance._filters')
<p class="muted">Giao dịch đại diện theo từng đơn. Mở chi tiết đơn để xem toàn bộ các lần thanh toán. Chỉ COD được cập nhật thủ công; xác nhận hoàn tiền sau khi đã trả tiền thực tế.</p>
<div class="panel p-3 table-responsive"><table class="table align-middle"><thead><tr><th>Đơn / ngày</th><th>Người nhận</th><th>Phương thức</th><th>Tổng tiền</th><th>Thanh toán</th><th>Cập nhật COD</th></tr></thead><tbody>
@forelse($orders as $order)
<tr><td><a href="{{ route('admin.orders.show',$order->id) }}">{{ $order->order_code }}</a><br><small>{{ $order->created_at }}</small></td><td>{{ $order->customer_name }}<br>{{ $order->phone }}</td><td>{{ $methods[$order->gateway] ?? $order->gateway }}</td><td>{{ number_format($order->total_amount) }} đ</td><td>{{ $statuses[$order->finance_status] ?? $order->finance_status }}</td><td>
@if($order->gateway==='cod' && isset($codTransitions[$order->finance_status]))
<form method="POST" action="{{ route('admin.finance.update-status',$order->id) }}" class="d-flex gap-2" onsubmit="return confirm('Xác nhận cập nhật thanh toán COD? Chỉ chọn đã thu/hoàn khi tiền thực tế đã được xử lý.')">@csrf @method('PATCH')
<input type="hidden" name="current_payment_id" value="{{ $order->payment_id ?? 0 }}"><input type="hidden" name="current_payment_status" value="{{ $order->finance_status }}"><input type="hidden" name="current_order_status" value="{{ $order->status }}">
<select class="form-select" name="payment_status" aria-label="Thanh toán đơn {{ $order->order_code }}">@foreach($codTransitions[$order->finance_status] as $status)<option value="{{ $status }}" @selected($status===$order->finance_status)>{{ $statuses[$status] ?? $status }}</option>@endforeach</select><button class="btn btn-admin">Lưu</button></form>
@else<span class="muted">Tự động qua cổng thanh toán</span>@endif
</td></tr>
@empty<tr><td colspan="6" class="py-4 text-center">Chưa có giao dịch phù hợp.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $orders->links() }}</div>
@endsection
