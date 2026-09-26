@extends('layouts.app')

@section('title', 'Đặt lại mật khẩu')

@section('content')
<main class="container section-space py-lg-5">
    <div class="auth-shell panel">
        <div class="auth-aside">
            <div class="auth-copy">
                <h2 class="h3 fw-bold mb-2">Tạo mật khẩu mới.</h2>
                <p class="mb-0">Sau khi hoàn tất, các phiên đăng nhập cũ sẽ bị thu hồi để bảo vệ tài khoản.</p>
            </div>
        </div>
        <div class="p-4 p-md-5 d-flex flex-column justify-content-center auth-form-panel">
            <h1 class="section-title h2 mb-2">Đặt lại mật khẩu</h1>
            <p class="text-secondary mb-4">Dùng ít nhất 12 ký tự, gồm chữ hoa, chữ thường, số và ký hiệu.</p>
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3">
                    <label class="form-label" for="reset-account-email">Email</label>
                    <input id="reset-account-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email) }}" autocomplete="email" required autofocus>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="reset-password">Mật khẩu mới</label>
                    <input id="reset-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="12" required aria-describedby="reset-password-help">
                    <div id="reset-password-help" class="form-text">Ít nhất 12 ký tự, có chữ hoa, chữ thường, số và ký hiệu.</div>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label" for="reset-password-confirmation">Nhập lại mật khẩu mới</label>
                    <input id="reset-password-confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
                <button class="btn btn-ocean w-100" data-loading-label="Đang cập nhật…">Đặt lại mật khẩu</button>
            </form>
        </div>
    </div>
</main>
@endsection
