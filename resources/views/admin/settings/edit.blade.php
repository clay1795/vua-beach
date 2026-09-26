@extends('layouts.admin')

@section('page-title', 'Cài đặt website')

@section('content')
    <div class="row g-4">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="panel p-4">
                @csrf
                @method('PATCH')

                <h2 class="h5 mb-1">Thông tin thương hiệu</h2>
                <p class="muted mb-4">Các nội dung này được hiển thị đồng bộ trên trang khách, trang quản trị và email.</p>

                <div class="mb-3">
                    <label class="form-label" for="siteName">Tên website</label>
                    <input id="siteName" name="site_name" class="form-control" value="{{ old('site_name', $settings->site_name) }}" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="siteTagline">Câu giới thiệu</label>
                    <input id="siteTagline" name="tagline" class="form-control" value="{{ old('tagline', $settings->tagline) }}" maxlength="255">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="supportEmail">Email hỗ trợ</label>
                        <input id="supportEmail" type="email" name="support_email" class="form-control" value="{{ old('support_email', $settings->support_email) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="supportPhone">Số điện thoại hỗ trợ</label>
                        <input id="supportPhone" name="support_phone" class="form-control" value="{{ old('support_phone', $settings->support_phone) }}">
                    </div>
                </div>

                <hr class="my-4">
                <h2 class="h5 mb-3">Logo website</h2>
                <label class="visually-hidden" for="siteLogo">Chọn tệp logo</label>
                <input id="siteLogo" type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                <div class="form-text">Dùng PNG, JPG hoặc WebP; nên chọn PNG nền trong suốt, dung lượng tối đa 2 MB.</div>
                @if($settings->logo_path)
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="removeLogo">
                        <label class="form-check-label" for="removeLogo">Xóa logo hiện tại và dùng biểu tượng mặc định</label>
                    </div>
                @endif

                <button class="btn btn-admin mt-4 px-4">Lưu cài đặt</button>
            </form>
        </div>
        <div class="col-xl-4">
            <div class="panel p-4 text-center">
                <p class="muted small text-uppercase mb-3">Xem trước thương hiệu</p>
                @if($settings->logo_url)
                    <img src="{{ $settings->logo_url }}" alt="Logo {{ $settings->site_name }}" width="180" height="60" loading="lazy" decoding="async" style="max-width:180px;max-height:110px;object-fit:contain">
                @else
                    <div class="stat-icon mx-auto mb-3"><i class="bi bi-water"></i></div>
                @endif
                <h3 class="h4 mt-3 mb-2">{{ $settings->site_name }}</h3>
                <p class="muted mb-0">{{ $settings->tagline }}</p>
            </div>
        </div>
    </div>
@endsection
