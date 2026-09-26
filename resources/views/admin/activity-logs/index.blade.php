@extends('layouts.admin')

@section('page-title', 'Nhật ký quản trị')

@section('content')
<div class="admin-page-head">
    <div><p class="admin-kicker">Bảo mật & truy vết</p><h2>Nhật ký thao tác</h2><p class="muted mb-0">Theo dõi các thay đổi quan trọng trong khu vực quản trị.</p></div>
    <form method="GET" class="admin-filter"><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Tìm thao tác"><button class="btn btn-outline-primary">Lọc</button></form>
</div>
<section class="admin-panel p-0 overflow-hidden mt-4">
    <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>Thời gian</th><th>Quản trị viên</th><th>Thao tác</th><th>Nội dung</th><th>Đối tượng</th></tr></thead><tbody>
    @forelse($logs as $log)<tr><td>{{ $log->created_at->format('d/m/Y H:i') }}</td><td>{{ $log->adminUser?->name ?? 'Hệ thống' }}</td><td><span class="badge text-bg-light border">{{ $log->action }}</span></td><td>{{ $log->description }}</td><td>{{ class_basename($log->subject_type) }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}</td></tr>@empty
    <tr><td colspan="5" class="text-center py-5 text-secondary">Chưa có thao tác nào được ghi nhận.</td></tr>@endforelse
    </tbody></table></div>
</section>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
