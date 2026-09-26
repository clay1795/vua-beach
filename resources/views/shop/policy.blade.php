@extends('layouts.app')
@php($policies = [
    'shipping' => ['Chính sách giao hàng', 'Giao hàng toàn quốc', [['Phạm vi giao hàng', 'Vua Beach giao hàng trên toàn quốc qua đơn vị vận chuyển phù hợp. Phí và thời gian giao dự kiến được hiển thị tại bước thanh toán.'], ['Theo dõi đơn hàng', 'Sau khi đơn được xác nhận, bạn có thể theo dõi mã vận đơn và trạng thái giao hàng trong mục Đơn hàng của tôi.']]],
    'returns' => ['Chính sách đổi trả', 'Đổi size trong 7 ngày', [['Điều kiện đổi trả', 'Sản phẩm còn nguyên tem, chưa qua sử dụng, chưa giặt và còn bao bì ban đầu.'], ['Cách yêu cầu hỗ trợ', 'Liên hệ cửa hàng kèm mã đơn hàng và hình ảnh sản phẩm để được hướng dẫn đổi size hoặc xử lý vấn đề.']]],
    'terms' => ['Điều khoản mua hàng', 'Thông tin minh bạch', [['Xác nhận đơn hàng', 'Đơn chỉ được xử lý sau khi khách cung cấp chính xác thông tin người nhận và cửa hàng xác nhận khả năng giao hàng.'], ['Bảo mật thông tin', 'Thông tin cá nhân chỉ được dùng để xử lý đơn hàng, hỗ trợ khách hàng và thực hiện nghĩa vụ liên quan.']]],
])
@section('title', $policies[$policy][0] ?? 'Chính sách mua hàng')
@section('content')
<main class="container section-space commerce-page policy-page"><header class="commerce-page-head"><div><p class="eyebrow">Vua Beach</p><h1>{{ $policies[$policy][0] ?? 'Chính sách mua hàng' }}</h1><p>{{ $policies[$policy][1] ?? '' }}</p></div></header><section class="panel policy-card">@foreach(($policies[$policy][2] ?? []) as [$title, $content])<article><h2>{{ $title }}</h2><p>{{ $content }}</p></article>@endforeach</section></main>
@endsection
