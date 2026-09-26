@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
<main class="container section-space py-lg-5">
    <div class="auth-shell panel">
        <div class="auth-aside">
            <div class="auth-copy">
                <h2 class="h3 fw-bold mb-2">Mùa hè đang chờ bạn.</h2>
                <p class="mb-0">Đăng nhập để tiếp tục mua sắm và theo dõi đơn hàng.</p>
            </div>
        </div>
        <div class="p-4 p-md-5 d-flex flex-column justify-content-center auth-form-panel">
            <h1 class="section-title h2 mb-2">Đăng nhập</h1>
            <p class="text-secondary mb-4">Chào mừng bạn trở lại {{ $siteSettings->site_name }}.</p>
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="username">Tên đăng nhập</label>
                    <input id="username" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" autocomplete="username" required autofocus aria-describedby="username-error">
                    @error('username')<div id="username-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required aria-describedby="login-password-error">
                    @error('password')<div id="login-password-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-check mb-4">
                    <input id="remember" class="form-check-input" type="checkbox" name="remember" value="1">
                    <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                    <a class="float-end fw-semibold" href="{{ route('password.request') }}">Quên mật khẩu?</a>
                </div>
                <button class="btn btn-ocean w-100">Đăng nhập</button>
            </form>
            <p class="text-center mt-4 mb-0">Chưa có tài khoản? <a class="fw-bold" href="{{ route('register') }}">Tạo tài khoản</a></p>
        </div>
    </div>
</main>
@endsection
