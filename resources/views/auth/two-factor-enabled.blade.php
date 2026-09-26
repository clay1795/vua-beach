@extends('layouts.app')
@section('title', 'Xác thực hai lớp')
@section('content')
<main class="container section-space py-lg-5">
    <section class="panel mx-auto p-4 p-md-5" style="max-width:620px">
        <p class="eyebrow">Bảo mật quản trị</p>
        <h1 class="section-title h2">Xác thực hai lớp đang bật</h1>
        <p class="text-secondary">Tài khoản quản trị này được bảo vệ bằng mã dùng một lần. Chỉ tắt khi bạn đang thay thiết bị xác thực.</p>
        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-4">@csrf @method('DELETE')
            <div class="mb-3"><label class="form-label" for="current-password">Mật khẩu hiện tại</label><input class="form-control" id="current-password" type="password" name="password" autocomplete="current-password" required></div>
            <div class="mb-3"><label class="form-label" for="disable-code">Mã xác thực 6 số</label><input class="form-control" id="disable-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div>
            <button class="btn btn-outline-danger" type="submit">Tắt xác thực hai lớp</button>
        </form>
    </section>
</main>
@endsection
