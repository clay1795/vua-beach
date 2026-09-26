<h2>Yêu cầu đổi trả #{{ $returnRequest->id }}</h2>
<p>Đơn hàng: <strong>{{ $returnRequest->order->order_code }}</strong></p>
<p>Trạng thái mới: <strong>{{ ['requested'=>'Đã gửi yêu cầu','approved'=>'Đã duyệt','rejected'=>'Đã từ chối','received'=>'Đã nhận hàng hoàn','completed'=>'Đã hoàn tất','cancelled'=>'Đã hủy'][$returnRequest->status] }}</strong></p>
@if($returnRequest->admin_note)<p>Ghi chú từ cửa hàng: {{ $returnRequest->admin_note }}</p>@endif
<p>Bạn có thể xem chi tiết trong mục Đơn hàng của tôi.</p>
