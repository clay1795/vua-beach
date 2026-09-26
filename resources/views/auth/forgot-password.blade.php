@extends('layouts.app')

@section('title', 'Quên mật khẩu')

@section('content')
<main class="container section-space py-lg-5">
    <div class="auth-shell panel">
        <div class="auth-aside">
            <div class="auth-copy">
                <h2 class="h3 fw-bold mb-2">Bạn sẽ sớm quay lại.</h2>
                <p class="mb-0">Nhập email tài khoản, chúng tôi sẽ gửi liên kết đặt lại mật khẩu an toàn.</p>
            </div>
        </div>
        <div class="p-4 p-md-5 d-flex flex-column justify-content-center auth-form-panel">
            <h1 class="section-title h2 mb-2">Quên mật khẩu</h1>
            <p class="text-secondary mb-4">Vì bảo mật, thông báo luôn giống nhau dù email có tồn tại hay không.</p>
            @if(session('status'))
                <div class="alert alert-success" role="status" aria-live="polite">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-4">
                    <label class="form-label" for="reset-email">Email tài khoản</label>
                    <input id="reset-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" autocomplete="email" required autofocus aria-describedby="reset-email-error">
                    @error('email')<div id="reset-email-error" class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-ocean w-100" data-loading-label="Đang gửi…">Gửi liên kết đặt lại</button>
            </form>
            <p class="text-center mt-4 mb-0"><a class="fw-bold" href="{{ route('login') }}">Quay lại đăng nhập</a></p>
        </div>
    </div>
</main>
@endsection
