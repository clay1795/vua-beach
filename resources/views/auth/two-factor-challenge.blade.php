@extends('layouts.app')
@section('title', 'Xác thực quản trị')
@section('content')
<main class="container section-space py-lg-5">
    <section class="panel mx-auto p-4 p-md-5" style="max-width:560px">
        <p class="eyebrow">Bước bảo mật</p>
        <h1 class="section-title h2">Xác thực quyền quản trị</h1>
        <p class="text-secondary">Nhập mã 6 số trong ứng dụng xác thực. Bạn cũng có thể dùng một mã khôi phục chưa sử dụng.</p>
        <form method="POST" action="{{ route('two-factor.verify') }}">@csrf
            <label class="form-label" for="two-factor-code">Mã xác thực hoặc mã khôi phục</label>
            <input class="form-control form-control-lg" id="two-factor-code" name="code" autocomplete="one-time-code" maxlength="32" required autofocus>
            <button class="btn btn-ocean w-100 mt-3" type="submit">Tiếp tục vào quản trị</button>
        </form>
    </section>
</main>
@endsection
