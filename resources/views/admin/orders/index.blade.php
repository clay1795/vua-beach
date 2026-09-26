@extends('layouts.admin')

@section('page-title', 'Quản lý đơn hàng')

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><p class="muted mb-0">Theo dõi và cập nhật trạng thái các đơn hàng của khách.</p><a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.orders.export', request()->except('page')) }}"><i class="bi bi-download"></i> Xuất CSV theo bộ lọc</a></div>
    <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Trạng thái vận chuyển">@foreach($tabs as $key=>$label)<a class="btn btn-sm {{ $activeTab===$key ? 'btn-admin':'btn-outline-secondary' }}" href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['tab'=>$key])) }}">{{ $label }} ({{ $key==='all' ? $counts->sum() : $counts->get($key,0) }})</a>@endforeach</nav>
    <form class="panel p-3 mb-3 row g-2" method="GET">
        @foreach(['gateway'=>['Phương thức',['cod'=>'COD','momo'=>'MoMo','vnpay'=>'VNPAY']], 'payment_status'=>['Thanh toán',\App\Models\Order::PAYMENT_STATUS_LABELS], 'sort'=>['Sắp xếp',['newest'=>'Mới nhất','oldest'=>'Cũ nhất','amount_asc'=>'Tiền tăng dần','amount_desc'=>'Tiền giảm dần']], 'per_page'=>['Số dòng',[25=>'25',50=>'50',100=>'100']]] as $key=>[$label,$options])
        <div class="col-md-3"><label class="form-label">{{ $label }}</label><select class="form-select" name="{{ $key }}"><option value="">Mặc định / tất cả</option>@foreach($options as $value=>$text)<option value="{{ $value }}" @selected((string)request($key)===(string)$value)>{{ $text }}</option>@endforeach</select></div>
        @endforeach
        <div class="col-md-4"><label class="visually-hidden" for="order-search">Tìm đơn hàng</label><input id="order-search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Mã đơn, khách hàng, email"></div>
        <div class="col-md-2"><label class="visually-hidden" for="order-status">Trạng thái đơn hàng</label><select id="order-status" class="form-select" name="status"><option value="">Mọi trạng thái</option>@foreach(\App\Models\Order::STATUS_LABELS as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="visually-hidden" for="order-from">Từ ngày</label><input id="order-from" class="form-control" type="date" name="from" value="{{ request('from') }}"></div><div class="col-md-2"><label class="visually-hidden" for="order-to">Đến ngày</label><input id="order-to" class="form-control" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-ocean flex-grow-1">Lọc</button><a class="btn btn-light" href="{{ route('admin.orders.index') }}">Xóa</a></div>
    </form>

    <div class="panel p-3 table-responsive">
        @if(request()->boolean('shipping_review'))
            <div class="alert alert-warning">Đang hiển thị các đơn GHN hoàn một phần cần đối soát. Mở từng đơn và xác nhận đúng sản phẩm thực nhận trước khi cập nhật tồn kho.</div>
        @endif
        @if ($orders->isEmpty())
            <div class="text-center py-5">
                <div class="admin-empty-icon"><i class="bi bi-receipt"></i></div>
                <h2 class="h5">Chưa có đơn hàng</h2>
                <p class="muted mb-0">Đơn hàng mới từ khách sẽ xuất hiện ở đây.</p>
            </div>
        @else
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Tổng tiền</th><th>Vận đơn GHN</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td><b>{{ $order->order_code }}</b></td>
                            <td><b>{{ $order->customer_name }}</b><br><small>{{ $order->phone }}</small></td>
                            <td>{{ $order->created_at->format('d/m/Y') }}</td>
                            <td>{{ number_format($order->total_amount, 0, ',', '.') }}đ</td>
                            <td>@if($order->ghn_order_code)<span class="badge text-bg-success">{{ $order->ghn_order_code }}</span><br><small class="{{ $order->shipping_status === 'partial_return' ? 'text-warning fw-bold' : 'muted' }}">{{ $order->shippingStatusLabel() }}</small>@else<span class="muted small">Chưa tạo</span>@endif</td>
                            <td><span class="status status-{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.orders.show', $order) }}">Xem đơn</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
@endsection
