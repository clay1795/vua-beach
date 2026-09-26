<!doctype html>
<html lang="vi">
<body style="margin:0;background:#f3f8f9;color:#173842;font-family:Arial,sans-serif">
    <div style="max-width:640px;margin:24px auto;background:#fff;padding:32px;border-radius:16px">
        <h1 style="margin:0 0 8px;color:#0d5367">Cảm ơn bạn đã đặt hàng</h1>
        <p>Mã đơn hàng: <strong>{{ $order->order_code }}</strong></p>
        <p>Vua Beach đã nhận đơn của bạn và sẽ sớm xác nhận.</p>
        <table width="100%" cellspacing="0" cellpadding="10" style="border-collapse:collapse">
            <thead><tr style="background:#edf5f6"><th align="left">Sản phẩm</th><th align="center">SL</th><th align="right">Thành tiền</th></tr></thead>
            <tbody>
            @foreach($order->items as $item)
                <tr style="border-bottom:1px solid #e5edef">
                    <td>
                        @if($item->product_image_url)<img src="{{ $item->product_image_url }}" alt="" width="52" height="52" style="object-fit:cover;vertical-align:middle;border-radius:6px;margin-right:8px">@endif
                        <strong>{{ $item->product_name }}</strong><br><small>{{ $item->color }} · Size {{ $item->size }}</small>
                    </td>
                    <td align="center">{{ $item->quantity }}</td><td align="right">{{ number_format($item->subtotal, 0, ',', '.') }}đ</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p style="text-align:right">
            Tiền hàng: <strong>{{ number_format($order->subtotal_amount, 0, ',', '.') }}đ</strong><br>
            @if($order->discount_amount > 0)
                Ưu đãi{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}: <strong style="color:#198754">-{{ number_format($order->discount_amount, 0, ',', '.') }}đ</strong><br>
            @endif
            Phí giao hàng: <strong>{{ number_format($order->shipping_fee, 0, ',', '.') }}đ</strong><br>
            <span style="font-size:18px">Tổng thanh toán: <strong>{{ number_format($order->total_amount, 0, ',', '.') }}đ</strong></span>
        </p>
        <p>Giao đến: {{ $order->customer_name }}, {{ $order->phone }}<br>{{ $order->address }}</p>
    </div>
</body>
</html>
