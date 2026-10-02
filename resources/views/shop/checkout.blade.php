@extends('layouts.app')

@section('title', 'Thanh toán')

@section('content')
<main class="container section-space commerce-page checkout-page">
    <header class="commerce-page-head">
        <div><p class="eyebrow">Thanh toán an toàn</p><h1>Hoàn tất đơn hàng</h1><p>Kiểm tra thông tin giao hàng và chọn phương thức thanh toán phù hợp.</p></div>
        <span class="secure-order-note"><i class="bi bi-shield-check"></i> Thông tin được bảo mật</span>
    </header>

    <form method="POST" action="{{ route('checkout.store') }}" class="row g-4">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
        @foreach($selectedVariantIds as $variantId)
            <input type="hidden" name="selected_variants[]" value="{{ $variantId }}">
        @endforeach

        <div class="col-lg-7">
            <section class="panel p-4 p-md-5 checkout-form-card">
                <div class="flow-section-title"><span>1</span><div><h2>Thông tin giao hàng</h2><p>Nhập thông tin người nhận chính xác.</p></div></div>

                @if($addresses->isNotEmpty())
                    <div class="mb-4">
                        <label class="form-label" for="shippingAddress">Chọn địa chỉ đã lưu</label>
                        <select name="shipping_address_id" class="form-select" id="shippingAddress">
                            <option value="">Nhập địa chỉ khác</option>
                            @foreach($addresses as $shippingAddress)
                                <option
                                    value="{{ $shippingAddress->id }}"
                                    data-name="{{ $shippingAddress->recipient_name }}"
                                    data-phone="{{ $shippingAddress->phone }}"
                                    data-address="{{ $shippingAddress->address }}"
                                    data-province-id="{{ $shippingAddress->province_id }}"
                                    data-district-id="{{ $shippingAddress->district_id }}"
                                    data-ward-code="{{ $shippingAddress->ward_code }}"
                                    @selected((int) old('shipping_address_id', $defaultAddress?->id) === $shippingAddress->id)
                                >
                                    {{ $shippingAddress->label }} - {{ $shippingAddress->recipient_name }}
                                    @if($shippingAddress->is_default) (Mặc định) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Địa chỉ mặc định được chọn sẵn. <a href="{{ route('profile.edit') }}">Quản lý sổ địa chỉ</a></div>
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="customerName">Họ và tên</label>
                        <input id="customerName" name="customer_name" class="form-control" value="{{ old('customer_name', $defaultAddress?->recipient_name ?? auth()->user()->name) }}" autocomplete="name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="customerPhone">Số điện thoại</label>
                        <input id="customerPhone" name="phone" class="form-control" value="{{ old('phone', $defaultAddress?->phone ?? auth()->user()->phone) }}" autocomplete="tel" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="customerEmail">Email</label>
                        <input id="customerEmail" type="email" name="email" class="form-control" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="customerAddress">Địa chỉ nhận hàng</label>
                        <textarea id="customerAddress" name="address" class="form-control" rows="3" autocomplete="street-address" required>{{ old('address', $defaultAddress?->address ?? auth()->user()->address) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Khu vực nhận hàng</label>
                        @if($ghnConfigured)
                            <div class="row g-2">
                                <div class="col-md-4"><label class="visually-hidden" for="provinceSelect">Tỉnh hoặc thành phố</label><select class="form-select" id="provinceSelect" aria-label="Tỉnh hoặc thành phố"><option>Đang tải tỉnh/thành...</option></select></div>
                                <div class="col-md-4"><label class="visually-hidden" for="districtSelect">Quận hoặc huyện</label><select class="form-select" id="districtSelect" name="to_district_id" aria-label="Quận hoặc huyện" disabled><option value="">Chọn quận/huyện</option></select></div>
                                <div class="col-md-4"><label class="visually-hidden" for="wardSelect">Phường hoặc xã</label><select class="form-select" id="wardSelect" name="to_ward_code" aria-label="Phường hoặc xã" disabled><option value="">Chọn phường/xã</option></select></div>
                            </div>
                            <div class="form-text">Phí được GHN tính theo khu vực nhận hàng.</div>
                        @else
                            <div class="shipping-unavailable"><i class="bi bi-truck"></i><span>Chưa cấu hình GHN: áp dụng phí giao hàng tạm tính {{ number_format($defaultShippingFee, 0, ',', '.') }}đ.</span></div>
                        @endif
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="orderNote">Ghi chú <span class="text-secondary fw-normal">(không bắt buộc)</span></label>
                        <textarea id="orderNote" name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>
                </div>

                <hr class="my-4">
                <div class="flow-section-title"><span>2</span><div><h2>Hình thức thanh toán</h2><p>Chọn một trong các hình thức bên dưới.</p></div></div>
                <div class="vstack gap-3">
                    <label class="payment-option">
                        <input class="form-check-input mt-0" type="radio" name="payment_method" value="cod" @checked(old('payment_method', 'cod') === 'cod')>
                        <span class="fs-4" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
                        <span><b>Thanh toán trực tiếp</b><br><small class="text-secondary">Thanh toán tiền mặt khi nhận hàng.</small></span>
                    </label>
                    <label class="payment-option {{ $momoAvailable ? '' : 'opacity-50' }}">
                        <input class="form-check-input mt-0" type="radio" name="payment_method" value="momo" @disabled(! $momoAvailable) @checked($momoAvailable && old('payment_method') === 'momo')>
                        <span class="momo-mark">M</span>
                        <span><b>Ví điện tử MoMo @if(config('services.payment_sandbox_mode') && filter_var(config('services.payment_sandbox_labels'), FILTER_VALIDATE_BOOLEAN))<span class="sandbox-chip">Sandbox</span>@endif</b><br><small class="text-secondary">{{ $momoAvailable ? (config('services.payment_sandbox_mode') ? 'Giao dịch thử nghiệm, không trừ tiền thật.' : 'Thanh toán nhanh bằng ứng dụng MoMo.') : 'MoMo chưa được cấu hình đầy đủ.' }}</small></span>
                    </label>
                    <label class="payment-option {{ $vnpayAvailable ? '' : 'opacity-50' }}">
                        <input class="form-check-input mt-0" type="radio" name="payment_method" value="vnpay" @disabled(! $vnpayAvailable) @checked($vnpayAvailable && old('payment_method') === 'vnpay')>
                        <span class="fs-4 text-primary" aria-hidden="true"><i class="bi bi-qr-code-scan"></i></span>
                        <span><b>VNPAY-QR / Ngân hàng @if(config('services.payment_sandbox_mode') && filter_var(config('services.payment_sandbox_labels'), FILTER_VALIDATE_BOOLEAN))<span class="sandbox-chip">Sandbox</span>@endif</b><br><small class="text-secondary">{{ $vnpayAvailable ? (config('services.payment_sandbox_mode') ? 'Giao dịch thử nghiệm, không trừ tiền thật.' : 'Quét QR hoặc thanh toán qua cổng VNPAY bảo mật.') : 'Đang chờ cấu hình mã đối tác VNPAY.' }}</small></span>
                    </label>
                </div>
                <div class="form-check mt-4 pt-2">
                    <input class="form-check-input" type="checkbox" value="1" name="accept_terms" id="acceptTerms" @checked(old('accept_terms')) required>
                    <label class="form-check-label small" for="acceptTerms">Tôi đã đọc và đồng ý với <a href="{{ route('policies.shipping') }}" target="_blank">chính sách giao hàng</a>, <a href="{{ route('policies.returns') }}" target="_blank">chính sách đổi trả</a> và <a href="{{ route('policies.terms') }}" target="_blank">điều khoản mua hàng</a>.</label>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <aside class="panel p-4 order-summary checkout-summary">
                <h2 class="h5 fw-bold mb-3">Sản phẩm đã chọn</h2>
                <div class="vstack gap-3">
                    @foreach($items as $item)
                        <div class="d-flex justify-content-between gap-3 small">
                            <span>{{ $item['variant']->product->name }} <span class="text-secondary">× {{ $item['quantity'] }}</span></span>
                            <b class="text-nowrap">{{ number_format($item['subtotal'], 0, ',', '.') }}đ</b>
                        </div>
                    @endforeach
                </div>
                <hr class="my-4">
                <div class="coupon-box">
                    <label class="form-label" for="couponCode">Mã ưu đãi <span class="text-secondary fw-normal">(nếu có)</span></label>
                    <div class="input-group"><input id="couponCode" class="form-control" name="coupon_code" value="{{ old('coupon_code') }}" maxlength="60" placeholder="Nhập mã giảm giá"><button class="btn btn-outline-secondary" id="applyCouponButton" type="button">Áp dụng</button></div>
                    <p class="form-text mb-0" id="couponMessage" aria-live="polite"></p>
                </div>
                <hr class="my-4">
                <div class="d-flex justify-content-between"><span class="text-secondary">Tiền hàng</span><strong>{{ number_format($total, 0, ',', '.') }}đ</strong></div>
                <div class="d-flex justify-content-between mt-2 d-none" id="discountRow"><span class="text-secondary">Ưu đãi</span><strong class="text-success" id="discountText">-0đ</strong></div>
                <div class="d-flex justify-content-between mt-2"><span class="text-secondary">Phí giao hàng</span><strong id="shippingFeeText">{{ number_format($defaultShippingFee, 0, ',', '.') }}đ</strong></div>
                <hr class="my-3">
                <div class="d-flex justify-content-between"><strong>Tổng thanh toán</strong><strong class="price fs-5" id="finalTotalText">{{ number_format($total + $defaultShippingFee, 0, ',', '.') }}đ</strong></div>
                <button class="btn btn-coral w-100 mt-4">Xác nhận đặt hàng <i class="bi bi-check2-circle ms-1"></i></button>
                <p class="small text-secondary text-center mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Thông tin của bạn được bảo mật.</p>
            </aside>
        </div>
    </form>
</main>

<script>
    document.getElementById('shippingAddress')?.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (!option.value) return;
        document.getElementById('customerName').value = option.dataset.name;
        document.getElementById('customerPhone').value = option.dataset.phone;
        document.getElementById('customerAddress').value = option.dataset.address;
        window.applySavedGhnLocation?.(option.dataset.provinceId, option.dataset.districtId, option.dataset.wardCode);
    });

    (() => {
        const feeText = document.getElementById('shippingFeeText'), totalText = document.getElementById('finalTotalText');
        const subtotal = {{ $total }}, weight = {{ $estimatedWeight }}, selectedVariants = @json($selectedVariantIds);
        let shippingFee = {{ $defaultShippingFee }}, discount = 0;
        const currency = value => new Intl.NumberFormat('vi-VN').format(value) + 'đ';
        const showTotal = () => {
            if (shippingFee === null) {
                feeText.textContent = 'Chọn đủ địa chỉ để tính phí';
                totalText.textContent = 'Chờ phí giao hàng';
                return;
            }
            feeText.textContent = currency(shippingFee);
            totalText.textContent = currency(Math.max(0, subtotal - discount) + shippingFee);
        };
        const discountRow = document.getElementById('discountRow'), discountText = document.getElementById('discountText'), couponMessage = document.getElementById('couponMessage');
        document.getElementById('applyCouponButton').addEventListener('click', async () => {
            const code = document.getElementById('couponCode').value.trim();
            if (!code) { couponMessage.textContent = 'Nhập mã ưu đãi để áp dụng.'; couponMessage.className = 'form-text text-danger mb-0'; return; }
            couponMessage.textContent = 'Đang kiểm tra mã...'; couponMessage.className = 'form-text mb-0';
            try {
                const response = await fetch('{{ route('checkout.coupon') }}', {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'}, body: JSON.stringify({coupon_code: code, selected_variants: selectedVariants})});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Mã ưu đãi không hợp lệ.');
                discount = Number(data.discount); discountText.textContent = '-' + currency(discount); discountRow.classList.remove('d-none'); couponMessage.textContent = data.name + ' đã được áp dụng.'; couponMessage.className = 'form-text text-success mb-0'; showTotal();
            } catch (error) { discount = 0; discountRow.classList.add('d-none'); couponMessage.textContent = error.message; couponMessage.className = 'form-text text-danger mb-0'; showTotal(); }
        });
        @if($ghnConfigured)
        const province = document.getElementById('provinceSelect'), district = document.getElementById('districtSelect'), ward = document.getElementById('wardSelect');
        let districtVersion = 0, wardVersion = 0, feeVersion = 0, savedVersion = 0;
        const resetSelect = (select, label) => {
            select.replaceChildren(new Option(label, ''));
            select.disabled = true;
        };
        const fillSelect = (select, items, key, label, placeholder, selected = '') => {
            select.replaceChildren(new Option(placeholder, ''), ...items.map(item => new Option(item[label], item[key])));
            select.disabled = items.length === 0;
            select.value = selected;
        };
        const requestLocation = async (url, init = {}) => {
            const response = await fetch(url, { ...init, headers: { Accept: 'application/json', ...init.headers } });
            if (!response.ok) throw new Error('Không tải được dữ liệu giao hàng.');
            const data = await response.json();
            if (data.code !== 200) throw new Error('Dịch vụ giao hàng chưa sẵn sàng.');
            return data;
        };
        const invalidateFee = () => {
            feeVersion++;
            shippingFee = null;
            feeText.textContent = 'Chọn đủ địa chỉ để tính phí';
            totalText.textContent = 'Chờ phí giao hàng';
        };
        const loadDistricts = async (selected = '') => {
            const version = ++districtVersion;
            wardVersion++;
            invalidateFee();
            resetSelect(district, 'Chọn quận/huyện');
            resetSelect(ward, 'Chọn phường/xã');
            if (!province.value) return false;
            resetSelect(district, 'Đang tải...');
            try {
                const data = await requestLocation('{{ url('/dia-diem/quan-huyen') }}/' + province.value);
                if (version !== districtVersion) return false;
                if (!Array.isArray(data.data)) throw new Error();
                fillSelect(district, data.data, 'DistrictID', 'DistrictName', data.data.length ? 'Chọn quận/huyện' : 'Chưa có quận/huyện', selected);
                return true;
            } catch {
                if (version === districtVersion) resetSelect(district, 'Lỗi tải — hãy chọn lại tỉnh/thành');
                return false;
            }
        };
        const loadWards = async (selected = '') => {
            const version = ++wardVersion;
            invalidateFee();
            resetSelect(ward, 'Chọn phường/xã');
            if (!district.value) return false;
            resetSelect(ward, 'Đang tải...');
            try {
                const data = await requestLocation('{{ url('/dia-diem/phuong-xa') }}/' + district.value);
                if (version !== wardVersion) return false;
                if (!Array.isArray(data.data)) throw new Error();
                fillSelect(ward, data.data, 'WardCode', 'WardName', data.data.length ? 'Chọn phường/xã' : 'Chưa có phường/xã', selected);
                return true;
            } catch {
                if (version === wardVersion) resetSelect(ward, 'Lỗi tải — hãy chọn lại quận/huyện');
                return false;
            }
        };
        window.applySavedGhnLocation = async (provinceId, districtId, wardCode) => {
            const version = ++savedVersion;
            province.value = provinceId || '';
            if (!await loadDistricts(districtId || '') || version !== savedVersion) return;
            if (!await loadWards(wardCode || '') || version !== savedVersion) return;
            ward.dispatchEvent(new Event('change'));
        };
        invalidateFee();
        requestLocation('{{ route('locations.provinces') }}').then(async data => {
            if (!Array.isArray(data.data)) throw new Error();
            fillSelect(province, data.data, 'ProvinceID', 'ProvinceName', 'Chọn tỉnh/thành');
            const selected = document.getElementById('shippingAddress')?.selectedOptions[0];
            if (selected?.value) await window.applySavedGhnLocation(selected.dataset.provinceId, selected.dataset.districtId, selected.dataset.wardCode);
        }).catch(() => resetSelect(province, 'Lỗi tải tỉnh/thành — tải lại trang'));
        province.addEventListener('change', () => { savedVersion++; loadDistricts(); });
        district.addEventListener('change', () => { savedVersion++; loadWards(); });
        ward.addEventListener('change', async () => {
            invalidateFee();
            if (!ward.value || !district.value) return;
            const version = feeVersion;
            feeText.textContent = 'Đang tính...';
            try {
                const data = await requestLocation('{{ route('locations.fee') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({to_district_id: district.value, to_ward_code: ward.value, weight}),
                });
                if (version !== feeVersion) return;
                const amount = data.data?.total;
                if (amount === null || amount === undefined || !Number.isFinite(Number(amount)) || Number(amount) < 0) throw new Error();
                shippingFee = Number(amount);
                showTotal();
            } catch {
                if (version === feeVersion) feeText.textContent = 'Lỗi tính phí — hãy chọn lại phường/xã';
            }
        });
        @endif
    })();
</script>
@endsection
