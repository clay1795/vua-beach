Cảnh báo vận hành Vua Beach

Sự kiện: {{ $event }}
Nguồn: {{ $job }}
Loại lỗi: {{ $exceptionClass }}
Môi trường: {{ app()->environment() }}
Thời điểm: {{ now()->toIso8601String() }}
@foreach ($details as $key => $value)
{{ $key }}: {{ $value }}
@endforeach

Nội dung lỗi và thông tin xác thực đã được loại bỏ. Vui lòng kiểm tra log mail có giới hạn quyền trên máy chủ.
