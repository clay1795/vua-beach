@extends('layouts.admin')
@section('page-title','Biểu đồ doanh thu')
@section('content')
@include('admin.reports._nav')
<div class="row g-4">@foreach($charts as $chart)<div class="col-lg-6"><section class="panel p-4"><h2 class="h5">{{ $chart['title'] }}</h2><div style="height:320px"><canvas data-report-chart="{{ json_encode($chart) }}" role="img" aria-label="{{ $chart['title'] }}"></canvas></div><a class="small" href="{{ route('admin.reports.index') }}">Xem bảng số liệu đầy đủ</a></section></div>@endforeach</div>
<noscript><p>Hãy xem bảng số liệu khi trình duyệt tắt JavaScript.</p></noscript>
@endsection
@push('scripts')
<script>
window.addEventListener('DOMContentLoaded', () => {
document.querySelectorAll('[data-report-chart]').forEach(canvas => {
if (typeof Chart === 'undefined') { canvas.parentElement.textContent='Không tải được biểu đồ. Vui lòng xem bảng số liệu.'; return; }
const d=JSON.parse(canvas.dataset.reportChart);
new Chart(canvas,{type:d.type,data:{labels:d.labels,datasets:[{label:'Doanh thu (đ)',data:d.values.map(Number),backgroundColor:['#0e7490','#38bdf8','#f59e0b','#059669','#64748b'],borderColor:'#0e7490'}]},options:{responsive:true,maintainAspectRatio:false}});
});
});
</script>
@endpush
