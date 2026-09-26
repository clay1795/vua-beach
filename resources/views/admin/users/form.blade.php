@extends('layouts.admin')
@section('page-title', $user->exists ? 'Sửa tài khoản' : 'Thêm tài khoản')
@section('content')
<form class="panel p-4" method="POST" action="{{ $user->exists ? route('admin.users.update',$user) : route('admin.users.store') }}">@csrf @if($user->exists) @method('PUT') @endif
<div class="row g-3">
@foreach(['name'=>'Họ tên','username'=>'Tên đăng nhập','email'=>'Email','password'=>'Mật khẩu','password_confirmation'=>'Nhập lại mật khẩu'] as $field=>$label)
<div class="col-md-6"><label class="form-label" for="user-{{ $field }}">{{ $label }}</label><input id="user-{{ $field }}" class="form-control" type="{{ str_starts_with($field,'password') ? 'password' : ($field==='email' ? 'email':'text') }}" name="{{ $field }}" value="{{ str_starts_with($field,'password') ? '' : old($field,$user->$field) }}" @if(!str_starts_with($field,'password') || !$user->exists) required @endif>@error($field)<div class="text-danger">{{ $message }}</div>@enderror</div>
@endforeach
<div class="col-md-6"><label class="form-label" for="user-role">Vai trò</label><select class="form-select" id="user-role" name="role"><option value="customer" @selected(old('role',$user->is_admin?'admin':'customer')==='customer')>Khách hàng</option><option value="admin" @selected(old('role',$user->is_admin?'admin':'customer')==='admin')>Quản trị viên</option></select></div>
</div><p class="muted mt-3">Mật khẩu tối thiểu 12 ký tự, gồm chữ hoa, chữ thường, số và ký hiệu. Khi sửa, để trống để giữ mật khẩu hiện tại. Thay email sẽ yêu cầu xác thực lại.</p>
<button class="btn btn-admin">Lưu tài khoản</button> <a class="btn btn-light" href="{{ route('admin.users.index') }}">Quay lại</a>
</form>
@endsection
