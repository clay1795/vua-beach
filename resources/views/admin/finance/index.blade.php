@extends('layouts.admin')
@section('page-title','Thống kê tài chính')
@section('content')
@include('admin.finance._filters')
<p class="muted">Mỗi đơn tính một lần; ưu tiên giao dịch đã thu hoặc hoàn tiền. Tổng giá trị đơn không đồng nghĩa doanh thu đã thu.</p>
<div class="row g-3 mb-4"><div class="col-md-6"><div class="panel p-4">Số đơn theo bộ lọc<h2>{{ number_format($summary->order_count) }}</h2></div></div><div class="col-md-6"><div class="panel p-4">Tổng giá trị đơn<h2>{{ number_format($summary->total_amount) }} đ</h2></div></div></div>
@foreach([['Theo trạng thái',$statusTotals,'finance_status',$statuses],['Theo phương thức',$methodTotals,'gateway',$methods]] as [$title,$rows,$key,$labels])
<div class="panel p-4 mb-4"><h2 class="h5">{{ $title }}</h2><div class="table-responsive"><table class="table"><thead><tr><th>Nhóm</th><th>Số đơn</th><th>Giá trị</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $labels[$row->$key] ?? $row->$key }}</td><td>{{ number_format($row->order_count) }}</td><td>{{ number_format($row->total_amount) }} đ</td></tr>
@empty<tr><td colspan="3">Chưa có dữ liệu phù hợp.</td></tr>@endforelse
</tbody></table></div></div>
@endforeach
@endsection
