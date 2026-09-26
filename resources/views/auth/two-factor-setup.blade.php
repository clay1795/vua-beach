@extends('layouts.app')
@section('title', 'Bật xác thực hai lớp')
@section('content')
<main class="container section-space py-lg-5">
    <section class="panel mx-auto p-4 p-md-5" style="max-width:760px">
        <p class="eyebrow">Bảo mật quản trị</p>
        <h1 class="section-title h2">Bật xác thực hai lớp</h1>
        <p class="text-secondary">Quét mã bằng Google Authenticator, Microsoft Authenticator hoặc ứng dụng tương thích. Sau đó nhập mã 6 số đang hiển thị.</p>
        <div class="row g-4 align-items-center mt-1">
            <div class="col-md-5 text-center"><div class="bg-white rounded-4 p-3 d-inline-block">{!! $qrCode !!}</div></div>
            <div class="col-md-7">
                <p class="small text-secondary mb-1">Không quét được? Nhập khóa thủ công:</p>
                <code class="d-block text-break p-3 bg-light rounded-3 mb-4">{{ $user->two_factor_secret }}</code>
                <form method="POST" action="{{ route('two-factor.confirm') }}">@csrf
                    <label class="form-label" for="two-factor-code">Mã xác thực 6 số</label>
                    <input class="form-control form-control-lg" id="two-factor-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
                    <button class="btn btn-ocean w-100 mt-3" type="submit">Xác nhận và bật bảo mật</button>
                </form>
            </div>
        </div>
    </section>
</main>
@endsection
