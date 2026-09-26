@extends('layouts.admin')
@section('page-title','Báo cáo doanh thu')
@section('content')
@include('admin.reports._nav')
<div class="row g-3 mb-4">@foreach(['Tổng đơn'=>$totalOrders,'Khách hàng'=>$totalCustomers,'Doanh thu (đ)'=>$totalRevenue] as $label=>$value)<div class="col-md-4"><div class="panel p-4">{{ $label }}<h2>{{ number_format($value) }}</h2></div></div>@endforeach</div>
<div class="panel p-4 mb-4"><h2 class="h5">Theo danh mục</h2><div class="table-responsive"><table class="table"><thead><tr><th>Danh mục</th><th>Số lượng bán</th><th>Giá trị sản phẩm</th></tr></thead><tbody>@forelse($categoryRevenue as $row)<tr><td>{{ $row->category_name ?? 'Không còn danh mục' }}</td><td>{{ number_format($row->total_qty) }}</td><td>{{ number_format($row->total_revenue) }} đ</td></tr>@empty<tr><td colspan="3">Chưa có doanh thu.</td></tr>@endforelse</tbody></table></div></div>
@foreach($periods as $title=>$rows)<div class="panel p-4 mb-4"><h2 class="h5">{{ $title }}</h2><div class="table-responsive"><table class="table"><thead><tr><th>Kỳ</th><th>Số đơn</th><th>Doanh thu</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->period }}</td><td>{{ number_format($row->order_count) }}</td><td>{{ number_format($row->total_revenue) }} đ</td></tr>@empty<tr><td colspan="3">Chưa có doanh thu.</td></tr>@endforelse</tbody></table></div></div>@endforeach
<div class="panel p-4"><h2 class="h5">Theo phương thức thanh toán</h2><table class="table"><thead><tr><th>Phương thức</th><th>Doanh thu</th></tr></thead><tbody>@forelse($methodRevenue as $row)<tr><td>{{ strtoupper($row->gateway) }}</td><td>{{ number_format($row->total_revenue) }} đ</td></tr>@empty<tr><td colspan="2">Chưa có doanh thu.</td></tr>@endforelse</tbody></table></div>
@endsection
