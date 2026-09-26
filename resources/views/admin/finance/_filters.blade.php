<nav class="d-flex gap-2 mb-3" aria-label="Tài chính">
<a class="btn btn-outline-secondary" href="{{ route('admin.finance.index', request()->query()) }}">Thống kê</a>
<a class="btn btn-outline-secondary" href="{{ route('admin.finance.transactions', request()->query()) }}">Giao dịch</a>
<a class="btn btn-outline-secondary" href="{{ route('admin.finance.export', request()->except('page')) }}">Xuất CSV</a>
</nav>
<form method="GET" class="panel p-3 mb-4 row g-3">
<div class="col-md-4"><label class="form-label">Mã đơn, người nhận, điện thoại</label><input class="form-control" name="search" value="{{ request('search') }}"></div>
@foreach(['date_from'=>'Từ ngày','date_to'=>'Đến ngày','min_amount'=>'Số tiền tối thiểu','max_amount'=>'Số tiền tối đa'] as $key=>$label)
<div class="col-md-2"><label class="form-label">{{ $label }}</label><input class="form-control" type="{{ str_starts_with($key,'date') ? 'date' : 'number' }}" name="{{ $key }}" value="{{ request($key) }}" @if(!str_starts_with($key,'date')) min="0" @endif></div>
@endforeach
@foreach(['gateway'=>['Phương thức',$methods],'payment_status'=>['Thanh toán',$statuses],'sort'=>['Sắp xếp',['newest'=>'Mới nhất','oldest'=>'Cũ nhất','amount_asc'=>'Tiền tăng dần','amount_desc'=>'Tiền giảm dần']]] as $key=>[$label,$options])
<div class="col-md-3"><label class="form-label">{{ $label }}</label><select name="{{ $key }}" class="form-select"><option value="">Tất cả / mặc định</option>@foreach($options as $value=>$text)<option value="{{ $value }}" @selected(request($key)===$value)>{{ $text }}</option>@endforeach</select></div>
@endforeach
<div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-admin">Lọc</button><a href="{{ url()->current() }}" class="btn btn-light">Xóa bộ lọc</a></div>
</form>
