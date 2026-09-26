@extends('layouts.app')
@section('title','Xác thực email')
@section('content')
<main class="verification-page">
    <div class="container">
        <section class="verification-card">
            <span class="verification-icon"><i class="bi bi-envelope-check"></i></span>
            <p class="eyebrow">Bảo vệ tài khoản</p>
            <h1>Kiểm tra email của bạn</h1>
            <p>Liên kết xác thực đang được gửi đến <strong>{{ auth()->user()->email }}</strong>. Hãy xác thực để thêm sản phẩm vào giỏ và xem lịch sử đơn hàng.</p>
            @if(session('status') === 'verification-link-sent')<div class="verification-success"><i class="bi bi-check-circle"></i>Đã đưa yêu cầu gửi lại liên kết xác thực vào hàng đợi.</div>@endif
            <div class="verification-actions">
                <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="btn btn-ocean" data-verification-resend data-sent-at="{{ session('verification_sent_at', 0) }}"><span data-resend-label>Gửi lại liên kết</span></button></form>
                <a class="btn btn-quiet" href="mailto:{{ auth()->user()->email }}">Mở ứng dụng email</a>
            </div>
            <p class="verification-hint">Liên kết có hiệu lực trong 60 phút. Nếu chưa thấy email, hãy kiểm tra thư mục Spam hoặc Thư rác.</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="verification-logout">Đăng xuất tài khoản</button></form>
        </section>
    </div>
</main>
@endsection
