@extends('layouts.app')
@section('title', 'Tài khoản của tôi')

@section('content')
<main class="account-page">
    <div class="container">
        <header class="account-page-header">
            <div>
                <p class="eyebrow">Không gian của bạn</p>
                <h1>Tài khoản của tôi</h1>
                <p>Cập nhật thông tin và quản lý địa chỉ nhận hàng trong cùng một nơi.</p>
            </div>
            <a class="account-orders-link" href="{{ route('orders.index') }}">
                <i class="bi bi-receipt"></i><span>Xem đơn hàng</span><i class="bi bi-arrow-right"></i>
            </a>
        </header>

        <div class="account-layout">
            <aside class="account-sidebar">
                <div class="profile-summary">
                    <span class="profile-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <div><h2>{{ $user->name }}</h2><p>{{ '@'.$user->username }}</p></div>
                </div>
                <div class="profile-status {{ $user->hasVerifiedEmail() ? 'is-verified' : '' }}">
                    <i class="bi {{ $user->hasVerifiedEmail() ? 'bi-patch-check-fill' : 'bi-exclamation-circle' }}"></i>
                    <span>{{ $user->hasVerifiedEmail() ? 'Email đã xác thực' : 'Email chưa xác thực' }}</span>
                </div>
                @if(! $user->hasVerifiedEmail())
                    <div class="profile-verification-actions">
                        <a href="{{ route('verification.notice') }}" class="profile-verify-link"><i class="bi bi-envelope-check"></i>Mở trang xác thực</a>
                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button type="submit" class="profile-resend-button" data-verification-resend data-sent-at="{{ session('verification_sent_at', 0) }}">
                                <i class="bi bi-send"></i><span data-resend-label>Gửi lại liên kết</span>
                            </button>
                        </form>
                    </div>
                @endif
                <dl class="profile-meta">
                    <div><dt>Loại tài khoản</dt><dd>{{ $user->is_admin ? 'Quản trị viên' : 'Khách hàng' }}</dd></div>
                    <div><dt>Địa chỉ đã lưu</dt><dd>{{ $addresses->count() }}</dd></div>
                </dl>
                @if($user->is_admin)
                    <a class="account-admin-link" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i><span>Mở trang quản trị</span></a>
                    <a class="account-admin-link mt-2" href="{{ route('two-factor.setup') }}"><i class="bi bi-shield-lock"></i><span>{{ $user->two_factor_confirmed_at ? 'Thiết lập lại bảo mật 2 lớp' : 'Bật bảo mật 2 lớp' }}</span></a>
                @endif
            </aside>

            <section class="account-content">
                <form method="POST" action="{{ route('profile.update') }}" class="account-section" data-validation-context="profile">
                    @csrf
                    @method('PATCH')
                    <div class="account-section-heading">
                        <span class="account-section-icon"><i class="bi bi-person"></i></span>
                        <div><h2>Thông tin cá nhân</h2><p>Thông tin dùng để liên hệ và xử lý đơn hàng.</p></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="profile-name">Họ và tên</label><input id="profile-name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
                        <div class="col-md-6"><label class="form-label" for="profile-username">Tên đăng nhập</label><input id="profile-username" name="username" class="form-control" value="{{ old('username', $user->username) }}" required></div>
                        <div class="col-md-7"><label class="form-label" for="profile-email">Email</label><input id="profile-email" type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
                        <div class="col-md-5"><label class="form-label" for="profile-phone">Số điện thoại</label><input id="profile-phone" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
                    </div>

                    <div class="account-divider"></div>
                    <div class="account-subheading"><div><h3>Bảo mật tài khoản</h3><p>Nhập mật khẩu hiện tại khi thay đổi email hoặc mật khẩu. Để trống mật khẩu mới nếu bạn muốn giữ nguyên.</p></div></div>
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label" for="profile-current-password">Mật khẩu hiện tại</label><input id="profile-current-password" type="password" name="current_password" class="form-control" autocomplete="current-password" aria-describedby="profile-current-password-help"><div id="profile-current-password-help" class="form-text">Bắt buộc khi đổi email hoặc đặt mật khẩu mới.</div></div>
                        <div class="col-md-6"><label class="form-label" for="profile-password">Mật khẩu mới</label><input id="profile-password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="12" aria-describedby="profile-password-help"><div id="profile-password-help" class="form-text">Ít nhất 12 ký tự, có chữ hoa, chữ thường, số và ký hiệu.</div></div>
                        <div class="col-md-6"><label class="form-label" for="profile-password-confirmation">Xác nhận mật khẩu mới</label><input id="profile-password-confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                    </div>
                    <div class="account-form-actions"><button class="btn btn-ocean">Lưu thay đổi</button></div>
                </form>

                <section class="account-section address-section">
                    <div class="account-section-heading address-heading">
                        <span class="account-section-icon"><i class="bi bi-geo-alt"></i></span>
                        <div><h2>Sổ địa chỉ</h2><p>Địa chỉ mặc định sẽ được chọn sẵn khi thanh toán.</p></div>
                        <span class="address-count">{{ $addresses->count() }} địa chỉ</span>
                    </div>

                    <div class="address-layout">
                        <form method="POST" action="{{ route('addresses.store') }}" class="address-form" data-validation-context="address-create">
                            @csrf
                            <h3>Thêm địa chỉ mới</h3>
                            <div class="mb-3"><label class="form-label" for="address-label">Tên gợi nhớ</label><input id="address-label" name="label" class="form-control" placeholder="Ví dụ: Nhà riêng" value="{{ old('label', 'Nhà riêng') }}" required></div>
                            <div class="mb-3"><label class="form-label" for="recipient-name">Người nhận</label><input id="recipient-name" name="recipient_name" class="form-control" value="{{ old('recipient_name', $user->name) }}" required></div>
                            <div class="mb-3"><label class="form-label" for="address-phone">Số điện thoại</label><input id="address-phone" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" required></div>
                            <div class="mb-3"><label class="form-label" for="address-detail">Địa chỉ chi tiết</label><textarea id="address-detail" name="address" class="form-control" rows="3" placeholder="Số nhà, tên đường" required>{{ old('address') }}</textarea></div>
                            <div class="row g-2 mb-3 address-location-picker" data-location-picker><div class="col-md-4"><select class="form-select" data-province aria-label="Tỉnh hoặc thành phố"><option>Đang tải tỉnh/thành...</option></select></div><div class="col-md-4"><select class="form-select" data-district aria-label="Quận hoặc huyện" disabled><option>Chọn quận/huyện</option></select></div><div class="col-md-4"><select class="form-select" data-ward aria-label="Phường hoặc xã" disabled><option>Chọn phường/xã</option></select></div><input type="hidden" name="province_id" data-province-id><input type="hidden" name="province_name" data-province-name><input type="hidden" name="district_id" data-district-id><input type="hidden" name="district_name" data-district-name><input type="hidden" name="ward_code" data-ward-code><input type="hidden" name="ward_name" data-ward-name></div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" value="1" name="is_default" id="makeDefault"><label class="form-check-label" for="makeDefault">Đặt làm địa chỉ mặc định</label></div>
                            <button class="btn btn-ocean w-100">Thêm địa chỉ</button>
                        </form>

                        <div class="address-list">
                            @forelse($addresses as $address)
                                <article class="address-card {{ $address->is_default ? 'is-default' : '' }}">
                                    <div class="address-card-head">
                                        <div>
                                            <div class="address-label-row"><h3>{{ $address->label }}</h3>@if($address->is_default)<span class="default-badge"><i class="bi bi-check"></i>Mặc định</span>@endif</div>
                                            <p class="address-recipient">{{ $address->recipient_name }} <span>{{ $address->phone }}</span></p>
                                        </div>
                                        <i class="bi bi-house-door address-card-icon"></i>
                                    </div>
                                    <p class="address-detail">{{ $address->address }}</p>
                                    @if($address->ward_name || $address->district_name || $address->province_name)<p class="address-detail text-secondary mb-2">{{ collect([$address->ward_name, $address->district_name, $address->province_name])->filter()->join(', ') }}</p>@endif
                                    <div class="address-actions">
                                        <button class="address-action" type="button" data-bs-toggle="collapse" data-bs-target="#address-edit-{{ $address->id }}"><i class="bi bi-pencil"></i>Sửa</button>
                                        @if(! $address->is_default)
                                            <form method="POST" action="{{ route('addresses.default', $address) }}">@csrf @method('PATCH')<button class="address-action" type="submit"><i class="bi bi-check-circle"></i>Đặt mặc định</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('addresses.destroy', $address) }}">@csrf @method('DELETE')<button class="address-action is-danger" type="submit" onclick="return confirm('Xóa địa chỉ này?')"><i class="bi bi-trash3"></i>Xóa</button></form>
                                    </div>
                                    <div class="collapse mt-3" id="address-edit-{{ $address->id }}">
                                        <form method="POST" action="{{ route('addresses.update', $address) }}" class="row g-2 border-top pt-3" data-validation-context="address-{{ $address->id }}">
                                            @csrf @method('PATCH')
                                            <div class="col-md-4"><input name="label" class="form-control" value="{{ $address->label }}" aria-label="Tên gợi nhớ địa chỉ" required></div>
                                            <div class="col-md-4"><input name="recipient_name" class="form-control" value="{{ $address->recipient_name }}" aria-label="Tên người nhận" required></div>
                                            <div class="col-md-4"><input name="phone" class="form-control" value="{{ $address->phone }}" aria-label="Số điện thoại người nhận" required></div>
                                            <div class="col-12"><textarea name="address" class="form-control" rows="2" aria-label="Địa chỉ chi tiết" required>{{ $address->address }}</textarea></div>
                                            <div class="row g-2 address-location-picker" data-location-picker data-province-id-value="{{ $address->province_id }}" data-district-id-value="{{ $address->district_id }}" data-ward-code-value="{{ $address->ward_code }}"><div class="col-md-4"><select class="form-select" data-province aria-label="Tỉnh hoặc thành phố"><option>Đang tải tỉnh/thành...</option></select></div><div class="col-md-4"><select class="form-select" data-district aria-label="Quận hoặc huyện" disabled><option>Chọn quận/huyện</option></select></div><div class="col-md-4"><select class="form-select" data-ward aria-label="Phường hoặc xã" disabled><option>Chọn phường/xã</option></select></div><input type="hidden" name="province_id" data-province-id value="{{ $address->province_id }}"><input type="hidden" name="province_name" data-province-name value="{{ $address->province_name }}"><input type="hidden" name="district_id" data-district-id value="{{ $address->district_id }}"><input type="hidden" name="district_name" data-district-name value="{{ $address->district_name }}"><input type="hidden" name="ward_code" data-ward-code value="{{ $address->ward_code }}"><input type="hidden" name="ward_name" data-ward-name value="{{ $address->ward_name }}"></div>
                                            <div class="col-12 d-flex gap-2"><button class="btn btn-ocean btn-sm">Lưu địa chỉ</button><button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#address-edit-{{ $address->id }}">Hủy</button></div>
                                        </form>
                                    </div>
                                </article>
                            @empty
                                <div class="address-empty"><span><i class="bi bi-geo-alt"></i></span><h3>Bạn chưa lưu địa chỉ nào</h3><p>Thêm địa chỉ để thanh toán nhanh hơn trong lần mua tiếp theo.</p></div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </section>
        </div>
    </div>
</main>
<script>
(() => {
    const request = async url => {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Không tải được dữ liệu giao hàng.');
        const data = await response.json();
        if (data.code !== 200) throw new Error('Dịch vụ giao hàng chưa sẵn sàng.');
        return data;
    };
    const resetSelect = (select, label) => {
        select.replaceChildren(new Option(label, ''));
        select.disabled = true;
    };
    const fillSelect = (select, items, key, label, placeholder, selected = '') => {
        select.replaceChildren(new Option(placeholder, ''), ...items.map(item => new Option(item[label], item[key])));
        select.disabled = items.length === 0;
        select.value = selected;
    };
    document.querySelectorAll('[data-location-picker]').forEach(async picker => {
        const province = picker.querySelector('[data-province]'), district = picker.querySelector('[data-district]'), ward = picker.querySelector('[data-ward]');
        const provinceId = picker.querySelector('[data-province-id]'), provinceName = picker.querySelector('[data-province-name]');
        const districtId = picker.querySelector('[data-district-id]'), districtName = picker.querySelector('[data-district-name]');
        const wardCode = picker.querySelector('[data-ward-code]'), wardName = picker.querySelector('[data-ward-name]');
        let districtVersion = 0, wardVersion = 0;
        const loadDistricts = async (selected = '', selectedWard = '') => {
            const version = ++districtVersion;
            wardVersion++;
            resetSelect(district, province.value ? 'Đang tải...' : 'Chọn quận/huyện');
            resetSelect(ward, 'Chọn phường/xã');
            if (!province.value) return false;
            try {
                const data = await request('{{ url('/dia-diem/quan-huyen') }}/' + province.value);
                if (version !== districtVersion) return false;
                if (!Array.isArray(data.data)) throw new Error();
                fillSelect(district, data.data, 'DistrictID', 'DistrictName', data.data.length ? 'Chọn quận/huyện' : 'Chưa có quận/huyện', selected);
                if (selected && district.value) await loadWards(selectedWard);
                return true;
            } catch {
                if (version === districtVersion) resetSelect(district, 'Lỗi tải — hãy chọn lại tỉnh/thành');
                return false;
            }
        };
        const loadWards = async (selected = '') => {
            const version = ++wardVersion;
            resetSelect(ward, district.value ? 'Đang tải...' : 'Chọn phường/xã');
            if (!district.value) return false;
            try {
                const data = await request('{{ url('/dia-diem/phuong-xa') }}/' + district.value);
                if (version !== wardVersion) return false;
                if (!Array.isArray(data.data)) throw new Error();
                fillSelect(ward, data.data, 'WardCode', 'WardName', data.data.length ? 'Chọn phường/xã' : 'Chưa có phường/xã', selected);
                return true;
            } catch {
                if (version === wardVersion) resetSelect(ward, 'Lỗi tải — hãy chọn lại quận/huyện');
                return false;
            }
        };
        try {
            const data = await request('{{ route('locations.provinces') }}');
            if (!Array.isArray(data.data)) throw new Error();
            fillSelect(province, data.data, 'ProvinceID', 'ProvinceName', 'Chọn tỉnh/thành');
            province.value = picker.dataset.provinceIdValue || provinceId.value;
            if (province.value) await loadDistricts(picker.dataset.districtIdValue || districtId.value, picker.dataset.wardCodeValue || wardCode.value);
        } catch { resetSelect(province, 'Lỗi tải tỉnh/thành — tải lại trang'); }
        province.addEventListener('change', () => {
            provinceId.value = province.value;
            provinceName.value = province.selectedOptions[0]?.text || '';
            districtId.value = districtName.value = wardCode.value = wardName.value = '';
            loadDistricts();
        });
        district.addEventListener('change', () => {
            districtId.value = district.value;
            districtName.value = district.selectedOptions[0]?.text || '';
            wardCode.value = wardName.value = '';
            loadWards();
        });
        ward.addEventListener('change', () => {
            wardCode.value = ward.value;
            wardName.value = ward.selectedOptions[0]?.text || '';
        });
    });
})();
</script>
@endsection
