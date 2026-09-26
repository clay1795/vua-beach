@extends('layouts.app')

@section('title', 'Tạo tài khoản')

@section('content')
<main class="container section-space py-lg-5">
    <div class="auth-shell panel">
        <div class="auth-aside">
            <div class="auth-copy">
                <h2 class="h3 fw-bold mb-2">Bắt đầu cùng {{ $siteSettings->site_name }}.</h2>
                <p class="mb-0">Lưu địa chỉ, theo dõi đơn hàng và mua sắm nhanh hơn.</p>
            </div>
        </div>
        <div class="p-4 p-md-5 d-flex flex-column justify-content-center auth-form-panel">
            <h1 class="section-title h2 mb-4">Tạo tài khoản</h1>
            <form method="POST" action="{{ route('register.store') }}">
                @csrf
                <div class="mb-3"><label class="form-label" for="name">Họ và tên</label><input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" autocomplete="name" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="mb-3"><label class="form-label" for="username">Tên đăng nhập</label><input id="username" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" autocomplete="username" required>@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Dùng chữ cái, số, dấu gạch nối hoặc gạch dưới.</div></div>
                <div class="mb-3"><label class="form-label" for="email">Email</label><input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" autocomplete="email" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="mb-3"><label class="form-label" for="phone">Số điện thoại</label><input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" autocomplete="tel" required>@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="password">Mật khẩu</label><input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="12" required aria-describedby="password-help"><div id="password-help" class="form-text">Ít nhất 12 ký tự, gồm chữ hoa, chữ thường, số và ký hiệu.</div>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label><input id="password_confirmation" type="password" name="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" autocomplete="new-password" required>@error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div>
                <button class="btn btn-ocean w-100 mt-4">Tạo tài khoản</button>
            </form>
            <p class="text-center mt-4 mb-0">Đã có tài khoản? <a class="fw-bold" href="{{ route('login') }}">Đăng nhập</a></p>
        </div>
    </div>
</main>
@endsection
