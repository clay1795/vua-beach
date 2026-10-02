<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10333f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $siteSettings->site_name)</title>
    <meta name="description" content="@yield('meta_description', $siteSettings->tagline)">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteSettings->site_name }}">
    <meta property="og:title" content="@yield('title', $siteSettings->site_name)">
    <meta property="og:description" content="@yield('meta_description', $siteSettings->tagline)">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@elseif($siteSettings->logo_url)<meta property="og:image" content="{{ $siteSettings->logo_url }}">@endif
    @if(request()->routeIs('home'))
        <link rel="preload" as="image" type="image/avif" href="{{ asset('images/vua-beach-hero-v2.avif') }}" fetchpriority="high">
    @endif
    @vite('resources/js/app.js')
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet">
    <script>document.documentElement.classList.add('has-js')</script>
</head>
<body>
    @php($cartCount = collect(session('cart', []))->sum('quantity'))
    <a class="skip-link" href="#main-content">Chuyển đến nội dung chính</a>
    @if(filter_var(config('services.payment_sandbox_mode'), FILTER_VALIDATE_BOOLEAN) && filter_var(config('services.payment_sandbox_labels'), FILTER_VALIDATE_BOOLEAN))
        <div class="payment-sandbox-banner" role="status" aria-label="Thông báo chế độ thanh toán thử nghiệm">
            <div class="container">
                <i class="bi bi-cone-striped" aria-hidden="true"></i>
                <span><strong>Chế độ thử nghiệm:</strong> MoMo và VNPAY đang dùng Sandbox. Không có giao dịch hay khoản tiền thật nào được xử lý.</span>
            </div>
        </div>
    @endif
    <header class="site-header">
        <div class="container header-main">
            <a class="header-hotline" href="tel:{{ preg_replace('/\s+/', '', $siteSettings->support_phone ?: '0969999999') }}">
                <i class="bi bi-telephone"></i>
                <span>Hotline: <strong>{{ $siteSettings->support_phone ?: '0969 999 999' }}</strong></span>
            </a>

            <a class="header-logo" href="{{ route('home') }}" aria-label="Trang chủ {{ $siteSettings->site_name }}">
                @if($siteSettings->logo_url)
                    <img class="brand-logo" src="{{ $siteSettings->logo_url }}" alt="Logo {{ $siteSettings->site_name }}" width="142" height="48" decoding="async">
                @else
                    <span class="brand-mark"><i class="bi bi-water"></i></span>
                @endif
            </a>

            <div class="header-actions">
                <form class="header-search" action="{{ route('products.index') }}" method="GET" role="search">
                    <label class="visually-hidden" for="header-search-input">Tìm sản phẩm</label>
                    <input id="header-search-input" name="q" value="{{ request('q') }}" placeholder="Tìm sản phẩm">
                    <button type="submit" aria-label="Tìm kiếm"><i class="bi bi-search"></i></button>
                </form>
                <div class="dropdown account-dropdown">
                    <button class="account-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Mở menu tài khoản">
                        <span class="account-avatar"><i class="bi bi-person"></i></span>
                        <span class="account-trigger-copy d-none d-xl-grid">
                            <small>{{ auth()->check() ? 'Xin chào' : 'Tài khoản' }}</small>
                            <strong>{{ auth()->check() ? Str::limit(auth()->user()->name, 16) : 'Đăng nhập' }}</strong>
                        </span>
                        <i class="bi bi-chevron-down account-chevron d-none d-xl-inline"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end account-menu">
                        @auth
                            <div class="account-menu-head">
                                <span class="account-menu-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                                <div>
                                    <strong>{{ auth()->user()->name }}</strong>
                                    <small>{{ auth()->user()->email }}</small>
                                </div>
                            </div>
                            <div class="account-menu-status {{ auth()->user()->hasVerifiedEmail() ? 'is-verified' : '' }}">
                                <i class="bi {{ auth()->user()->hasVerifiedEmail() ? 'bi-patch-check-fill' : 'bi-exclamation-circle' }}"></i>
                                {{ auth()->user()->hasVerifiedEmail() ? 'Email đã xác thực' : 'Email chưa xác thực' }}
                            </div>
                            <div class="account-menu-links">
                                <a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear"></i><span>Thông tin tài khoản</span></a>
                                @if(auth()->user()->is_admin)
                                    <a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i><span>Trang quản trị</span></a>
                                @else
                                    <a class="dropdown-item" href="{{ route('orders.index') }}"><i class="bi bi-receipt"></i><span>Đơn hàng của tôi</span></a>
                                @endif
                            </div>
                            <form class="account-menu-logout" method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"><i class="bi bi-box-arrow-right"></i><span>Đăng xuất</span></button>
                            </form>
                        @else
                            <div class="account-guest-head">
                                <span class="account-menu-avatar"><i class="bi bi-person"></i></span>
                                <div><strong>Chào bạn</strong><small>Đăng nhập để mua sắm thuận tiện hơn</small></div>
                            </div>
                            <div class="account-guest-actions">
                                <a class="btn btn-ocean w-100" href="{{ route('login') }}">Đăng nhập</a>
                                <a class="account-create-link" href="{{ route('register') }}">Tạo tài khoản mới <i class="bi bi-arrow-right"></i></a>
                            </div>
                        @endauth
                    </div>
                </div>
                <a class="header-icon header-cart" href="{{ route('cart.index') }}" aria-label="Giỏ hàng">
                    <i class="bi bi-bag"></i>
                    @if($cartCount > 0)<span class="cart-count" aria-label="{{ $cartCount }} sản phẩm trong giỏ">{{ $cartCount }}</span>@endif
                </a>
            </div>
        </div>

        <nav class="navbar navbar-expand-lg category-navbar" aria-label="Danh mục chính">
            <div class="container">
                <button class="navbar-toggler border-0 ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Danh mục">
                    <i class="bi bi-list fs-3"></i><span class="ms-2">Danh mục</span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav mx-auto align-items-lg-center">
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Trang chủ</a></li>
                        @foreach($navCategories as $navCategory)
                            <li class="nav-item"><a class="nav-link {{ request('category') === $navCategory->slug ? 'active' : '' }}" href="{{ route('products.index', ['category' => $navCategory->slug]) }}" @if(request('category') === $navCategory->slug) aria-current="page" @endif>{{ $navCategory->name }}</a></li>
                        @endforeach
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('products.*') && !request('category') ? 'active' : '' }}" href="{{ route('products.index') }}" @if(request()->routeIs('products.*') && !request('category')) aria-current="page" @endif>Tất cả sản phẩm</a></li>
                        @auth
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">Đơn hàng của tôi</a></li>
                            @if(auth()->user()->is_admin)<li class="nav-item d-lg-none"><a class="nav-link" href="{{ route('admin.dashboard') }}">Trang quản trị</a></li>@endif
                            <li class="nav-item d-lg-none">
                                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-link nav-link">Đăng xuất</button></form>
                            </li>
                        @else
                            <li class="nav-item d-lg-none"><a class="nav-link" href="{{ route('register') }}">Tạo tài khoản</a></li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    @if(session('success'))
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="status" aria-live="polite">
                {{ session('success') }}
                <button class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="container mt-3">
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm" role="status" aria-live="polite">
                {{ session('warning') }}
                <button class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
            </div>
        </div>
    @endif

    @if(session('payment_notice') === 'cancelled')
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-start gap-3" role="alert">
                <i class="bi bi-x-circle-fill fs-4"></i>
                <div><strong>Đơn hàng đã được hủy</strong><br><span>Giao dịch MoMo không được hoàn tất. Sản phẩm đã được hoàn lại kho.</span></div>
                <button class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Đóng"></button>
            </div>
        </div>
    @elseif(session('payment_notice') === 'failed')
        <div class="container mt-3">
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-start gap-3" role="alert">
                <i class="bi bi-exclamation-circle-fill fs-4"></i>
                <div><strong>Thanh toán MoMo chưa thành công</strong><br><span>Đơn hàng vẫn được giữ. Khi MoMo gửi IPN xác nhận, bạn có thể thanh toán lại trên trang chi tiết đơn hàng.</span></div>
                <button class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Đóng"></button>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="container mt-3">
            <div class="alert alert-danger border-0 shadow-sm" role="alert" aria-live="assertive" data-validation-summary data-validation-context="{{ session('validation_context', '') }}">
                <ul class="mb-0">@foreach($errors->messages() as $field => $messages)@foreach($messages as $message)<li data-validation-field="{{ $field }}">{{ $message }}</li>@endforeach @endforeach</ul>
            </div>
        </div>
    @endif

    <div id="main-content">
        @yield('content')
    </div>

    <nav class="mobile-dock" aria-label="Điều hướng nhanh trên điện thoại">
        <a class="{{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><i class="bi bi-house"></i><span>Trang chủ</span></a>
        <a class="{{ request()->routeIs('products.*') ? 'is-active' : '' }}" href="{{ route('products.index') }}" @if(request()->routeIs('products.*')) aria-current="page" @endif><i class="bi bi-grid"></i><span>Sản phẩm</span></a>
        <a class="{{ request()->routeIs('cart.*') ? 'is-active' : '' }}" href="{{ route('cart.index') }}" @if(request()->routeIs('cart.*')) aria-current="page" @endif><span class="mobile-dock-cart"><i class="bi bi-bag"></i>@if($cartCount > 0)<b aria-label="{{ $cartCount }} sản phẩm trong giỏ">{{ $cartCount }}</b>@endif</span><span>Giỏ hàng</span></a>
        @auth
            <a class="{{ request()->routeIs('profile.*', 'orders.*') ? 'is-active' : '' }}" href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*', 'orders.*')) aria-current="page" @endif><i class="bi bi-person"></i><span>Tài khoản</span></a>
        @else
            <a class="{{ request()->routeIs('login') ? 'is-active' : '' }}" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif><i class="bi bi-person"></i><span>Đăng nhập</span></a>
        @endauth
    </nav>

    <footer class="footer footer-compact">
        <div class="container">
            <div class="footer-compact-main">
                <a class="footer-compact-brand" href="{{ route('home') }}" aria-label="Trang chủ {{ $siteSettings->site_name }}">
                    @if($siteSettings->logo_url)
                        <img class="brand-logo" src="{{ $siteSettings->logo_url }}" alt="Logo {{ $siteSettings->site_name }}" width="124" height="34" loading="lazy" decoding="async">
                    @else
                        <span class="brand-mark"><i class="bi bi-water"></i></span>
                    @endif
                </a>

                <nav class="footer-compact-links" aria-label="Liên kết cuối trang">
                    <a href="{{ route('products.index') }}">Sản phẩm</a>
                    <a href="{{ route('cart.index') }}">Giỏ hàng</a>
                    @auth
                        <a href="{{ route('profile.edit') }}">Tài khoản</a>
                        <a href="{{ route('orders.index') }}">Đơn hàng</a>
                        @if(auth()->user()->is_admin)<a href="{{ route('admin.dashboard') }}">Quản trị</a>@endif
                    @else
                        <a href="{{ route('login') }}">Đăng nhập</a>
                    @endauth
                    <a href="{{ route('policies.shipping') }}">Giao hàng</a>
                    <a href="{{ route('policies.returns') }}">Đổi trả</a>
                    <a href="{{ route('policies.terms') }}">Điều khoản</a>
                </nav>

                <div class="footer-compact-contact" aria-label="Thông tin hỗ trợ">
                    @if($siteSettings->support_phone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $siteSettings->support_phone) }}" aria-label="Gọi {{ $siteSettings->support_phone }}"><i class="bi bi-telephone"></i><span>{{ $siteSettings->support_phone }}</span></a>
                    @endif
                    @if($siteSettings->support_email)
                        <a href="mailto:{{ $siteSettings->support_email }}" aria-label="Gửi email tới {{ $siteSettings->support_email }}"><i class="bi bi-envelope"></i><span>{{ $siteSettings->support_email }}</span></a>
                    @endif
                </div>
            </div>

            <div class="footer-compact-bottom">
                <span>© {{ date('Y') }} {{ $siteSettings->site_name }}</span>
                <div class="footer-compact-services" aria-label="Cam kết dịch vụ">
                    <span><i class="bi bi-truck"></i> Giao hàng toàn quốc</span>
                    <span><i class="bi bi-arrow-repeat"></i> Đổi size trong 7 ngày</span>
                </div>
            </div>
        </div>
    </footer>

    <script>
    document.querySelectorAll('label.form-label:not([for])').forEach((label, index) => {
        if (label.querySelector('input, select, textarea')) return;
        const control = label.parentElement?.querySelector('input:not([type="hidden"]), select, textarea');
        if (!control) return;
        control.id ||= `store-field-${index}`;
        label.htmlFor = control.id;
    });

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target);
            });
        }, { threshold: .12 });
        document.querySelectorAll('[data-reveal]').forEach((element) => revealObserver.observe(element));
    } else {
        document.querySelectorAll('[data-reveal]').forEach((element) => element.classList.add('is-revealed'));
    }
    window.setTimeout(() => document.querySelectorAll('[data-reveal]:not(.is-revealed)').forEach((element) => element.classList.add('is-revealed')), 1200);
    </script>
    @stack('scripts')
</body>
</html>
