<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('page-title', 'Quản trị cửa hàng') | {{ $siteSettings->site_name }}</title>
    @vite('resources/js/admin.js')
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}" aria-label="Trang tổng quan {{ $siteSettings->site_name }}">
            @if($siteSettings->logo_url)
                <img src="{{ $siteSettings->logo_url }}" alt="Logo {{ $siteSettings->site_name }}" width="180" height="52" decoding="async">
            @else
                <span class="admin-brand-mark"><i class="bi bi-water"></i></span><span>{{ $siteSettings->site_name }}</span>
            @endif
        </a>
        <p class="admin-nav-label">Quản lý cửa hàng</p>
        <nav class="admin-nav" aria-label="Báo cáo và tài chính">
            <a class="admin-nav-link" href="{{ route('admin.reports.index') }}"><i class="bi bi-bar-chart"></i><span>Báo cáo doanh thu</span></a>
            <a class="admin-nav-link" href="{{ route('admin.finance.index') }}"><i class="bi bi-cash-stack"></i><span>Tài chính</span></a>
        </nav>
        <nav class="admin-nav" aria-label="Điều hướng quản trị">
            <a class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid-1x2"></i><span>Tổng quan</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><i class="bi bi-bag"></i><span>Sản phẩm</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}" href="{{ route('admin.inventory.index') }}"><i class="bi bi-box-seam"></i><span>Tồn kho</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.purchase-receipts.*') ? 'active' : '' }}" href="{{ route('admin.purchase-receipts.index') }}"><i class="bi bi-box-arrow-in-down"></i><span>Nhập kho</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.suppliers.*') ? 'active' : '' }}" href="{{ route('admin.suppliers.index') }}"><i class="bi bi-truck"></i><span>Nhà cung cấp</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-collection"></i><span>Danh mục</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="bi bi-receipt"></i><span>Đơn hàng</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.returns.*') ? 'active' : '' }}" href="{{ route('admin.returns.index') }}"><i class="bi bi-arrow-left-right"></i><span>Đổi trả</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}"><i class="bi bi-ticket-perforated"></i><span>Mã ưu đãi</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="bi bi-people"></i><span>Tài khoản</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}"><i class="bi bi-clock-history"></i><span>Nhật ký quản trị</span></a>
            <a class="admin-nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}"><i class="bi bi-sliders"></i><span>Cài đặt website</span></a>
        </nav>
        <div class="admin-sidebar-user">
            <span class="admin-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <div><strong>{{ auth()->user()->name }}</strong><small>Quản trị viên</small></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" title="Đăng xuất" aria-label="Đăng xuất"><i class="bi bi-box-arrow-right"></i></button></form>
        </div>
    </aside>
    <div class="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileNav" aria-label="Mở menu"><i class="bi bi-list"></i></button>
            <div class="admin-heading"><p>{{ mb_strtoupper($siteSettings->site_name) }}</p><h1>@yield('page-title', 'Quản trị cửa hàng')</h1></div>
            <a class="admin-store-link" href="{{ route('home') }}" target="_blank" rel="noopener" aria-label="Xem website (mở trong thẻ mới)"><i class="bi bi-arrow-up-right" aria-hidden="true"></i><span>Xem website</span></a>
        </header>
        @if(session('success'))<div class="admin-alert alert alert-success alert-dismissible fade show" role="status" aria-live="polite"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo"></button></div>@endif
        @if(session('error'))<div class="admin-alert alert alert-warning alert-dismissible fade show" role="alert" aria-live="assertive"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert" aria-label="Đóng thông báo"></button></div>@endif
        @if(session('recovery_codes'))<div class="admin-alert alert alert-warning" role="alert" aria-live="assertive"><h2 class="h6 fw-bold">Lưu mã khôi phục ngay bây giờ</h2><p>Mỗi mã chỉ dùng được một lần. Hãy lưu trong trình quản lý mật khẩu; hệ thống sẽ không hiển thị lại.</p><div class="row row-cols-2 row-cols-md-4 g-2">@foreach(session('recovery_codes') as $code)<div><code>{{ $code }}</code></div>@endforeach</div></div>@endif
        @if($errors->any())<div class="admin-alert alert alert-danger" role="alert" aria-live="assertive" data-validation-summary data-validation-context="{{ session('validation_context', '') }}"><ul class="mb-0">@foreach($errors->messages() as $field => $messages)@foreach($messages as $message)<li data-validation-field="{{ $field }}">{{ $message }}</li>@endforeach @endforeach</ul></div>@endif
        <main class="admin-content">@yield('content')</main>
    </div>
</div>
<div class="offcanvas offcanvas-start admin-mobile-nav" tabindex="-1" id="adminMobileNav">
    <div class="offcanvas-header"><strong>Quản trị {{ $siteSettings->site_name }}</strong><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Đóng menu quản trị"></button></div>
    <div class="offcanvas-body"><nav class="admin-nav" aria-label="Điều hướng quản trị trên điện thoại">
        <a class="admin-nav-link" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid-1x2"></i><span>Tổng quan</span></a>
        <a class="admin-nav-link" href="{{ route('admin.products.index') }}"><i class="bi bi-bag"></i><span>Sản phẩm</span></a>
        <a class="admin-nav-link" href="{{ route('admin.inventory.index') }}"><i class="bi bi-box-seam"></i><span>Tồn kho</span></a>
        <a class="admin-nav-link" href="{{ route('admin.purchase-receipts.index') }}"><i class="bi bi-box-arrow-in-down"></i><span>Nhập kho</span></a>
        <a class="admin-nav-link" href="{{ route('admin.suppliers.index') }}"><i class="bi bi-truck"></i><span>Nhà cung cấp</span></a>
        <a class="admin-nav-link" href="{{ route('admin.categories.index') }}"><i class="bi bi-collection"></i><span>Danh mục</span></a>
        <a class="admin-nav-link" href="{{ route('admin.orders.index') }}"><i class="bi bi-receipt"></i><span>Đơn hàng</span></a>
        <a class="admin-nav-link" href="{{ route('admin.returns.index') }}"><i class="bi bi-arrow-left-right"></i><span>Đổi trả</span></a>
        <a class="admin-nav-link" href="{{ route('admin.coupons.index') }}"><i class="bi bi-ticket-perforated"></i><span>Mã ưu đãi</span></a>
        <a class="admin-nav-link" href="{{ route('admin.users.index') }}"><i class="bi bi-people"></i><span>Tài khoản</span></a>
        <a class="admin-nav-link" href="{{ route('admin.activity-logs.index') }}"><i class="bi bi-clock-history"></i><span>Nhật ký quản trị</span></a>
        <a class="admin-nav-link" href="{{ route('admin.settings.edit') }}"><i class="bi bi-sliders"></i><span>Cài đặt website</span></a>
    </nav>
        <nav class="admin-nav" aria-label="Báo cáo trên điện thoại"><a class="admin-nav-link" href="{{ route('admin.reports.index') }}">Báo cáo doanh thu</a><a class="admin-nav-link" href="{{ route('admin.finance.index') }}">Tài chính</a></nav>
        <div class="admin-mobile-user mt-4 pt-3 border-top border-light border-opacity-25">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="admin-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <div><strong class="d-block">{{ auth()->user()->name }}</strong><small class="text-white-50">Quản trị viên</small></div>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-light w-100" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</button></form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('label.form-label:not([for])').forEach((label, index) => {
    if (label.querySelector('input, select, textarea')) return;
    const control = label.parentElement?.querySelector('input:not([type="hidden"]), select, textarea');
    if (!control) return;
    control.id ||= `admin-field-${index}`;
    label.htmlFor = control.id;
});
document.querySelectorAll('.table-responsive').forEach((region) => {
    if (region.scrollWidth <= region.clientWidth) return;
    region.tabIndex = 0;
    region.setAttribute('role', 'region');
    region.setAttribute('aria-label', 'Bảng dữ liệu có thể cuộn ngang');
});
</script>
@stack('scripts')
</body>
</html>
