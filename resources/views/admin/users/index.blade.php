@extends('layouts.admin')

@section('page-title', 'Quản lý tài khoản')

@section('content')
    <a class="btn btn-admin mb-3" href="{{ route('admin.users.create') }}">Thêm tài khoản</a>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <p class="muted mb-1">Quản lý quyền truy cập của khách hàng và quản trị viên.</p>
            <small class="muted">Khách hàng chỉ mua sắm, quản trị viên có thể quản lý cửa hàng.</small>
        </div>
        <form method="GET" class="d-flex gap-2" style="min-width:min(100%, 360px)">
            <input class="form-control" name="q" value="{{ $search }}" placeholder="Tìm tên, tài khoản hoặc email">
            <button class="btn btn-admin px-3">Tìm</button>
        </form>
    </div>

    <div class="panel p-3 table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Tài khoản</th>
                    <th>Liên hệ</th>
                    <th>Xác thực email</th>
                    <th>Vai trò hiện tại</th>
                    <th class="text-end">Cập nhật quyền</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <a href="{{ route('admin.users.show',$user) }}"><b>{{ $user->name }}</b></a>
                            <a class="small ms-2" href="{{ route('admin.users.edit',$user) }}">Sửa</a>
                            <br><small class="muted">{{ $user->username ? '@'.$user->username : 'Chưa đặt tên đăng nhập' }}</small>
                        </td>
                        <td>
                            <div>{{ $user->email }}</div>
                            @if($user->phone)<small class="muted">{{ $user->phone }}</small>@endif
                        </td>
                        <td>
                            @if ($user->hasVerifiedEmail())
                                <span class="badge text-bg-success">Đã xác thực</span>
                            @else
                                <span class="badge text-bg-secondary">Chưa xác thực</span>
                            @endif
                        </td>
                        <td>
                            @if ($user->is_admin)
                                <span class="badge text-bg-primary">Quản trị viên</span>
                            @else
                                <span class="badge text-bg-light border text-dark">Khách hàng</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($user->is(auth()->user()))
                                <small class="muted">Tài khoản đang đăng nhập</small>
                            @else
                                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="d-inline-flex gap-2 align-items-center">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role" class="form-select form-select-sm" aria-label="Vai trò của {{ $user->name }}">
                                        <option value="customer" @selected(! $user->is_admin)>Khách hàng</option>
                                        <option value="admin" @selected($user->is_admin)>Quản trị viên</option>
                                    </select>
                                    <button class="btn btn-sm btn-admin text-nowrap">Lưu quyền</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center muted py-5">Không tìm thấy tài khoản nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
