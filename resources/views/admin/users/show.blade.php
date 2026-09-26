@extends('layouts.admin')
@section('page-title','Thông tin tài khoản')
@section('content')
<div class="panel p-4"><h2 class="h4">{{ $user->name }}</h2><dl><dt>Tên đăng nhập</dt><dd>{{ $user->username }}</dd><dt>Email</dt><dd>{{ $user->email }}</dd><dt>Vai trò</dt><dd>{{ $user->is_admin ? 'Quản trị viên' : 'Khách hàng' }}</dd><dt>Số đơn hàng</dt><dd>{{ $user->orders_count }}</dd><dt>Xác thực email</dt><dd>{{ $user->hasVerifiedEmail() ? 'Đã xác thực' : 'Chưa xác thực' }}</dd></dl>
<div class="d-flex gap-2"><a class="btn btn-admin" href="{{ route('admin.users.edit',$user) }}">Sửa</a><a class="btn btn-light" href="{{ route('admin.users.index') }}">Quay lại</a>
@if(!$user->is(auth()->user()) && !$user->orders_count)<form method="POST" action="{{ route('admin.users.destroy',$user) }}" onsubmit="return confirm('Xóa tài khoản này?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Xóa tài khoản</button></form>@endif</div></div>
@endsection
