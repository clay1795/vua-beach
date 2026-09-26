# Quy tắc nghiệp vụ Vua Beach

Tài liệu này là nguồn chuẩn khi vận hành, kiểm thử và xử lý sự cố. Không cập nhật trực tiếp trạng thái hoặc tồn kho trong database; mọi thay đổi phải đi qua ứng dụng để giữ lịch sử và idempotency.

## Đơn hàng và thanh toán

- Đơn hàng: `pending → confirmed → shipping → completed`.
- Có thể hủy khi `pending` hoặc `confirmed`; hàng đã giữ được hoàn kho đúng một lần.
- Không hủy thủ công giao dịch online còn `pending`, vì IPN thành công có thể đang trên đường về. MoMo thất bại giữ nguyên đơn và lượng hàng đã dành; khách có thể thanh toán lại trên chính đơn đó.
- Khi admin hủy đơn online đã `paid`, thanh toán chuyển `refund_pending`; chỉ chuyển `refunded` sau khi đã hoàn tiền thực tế và admin xác nhận. Mọi lần bấm lặp là no-op.
- Khi đang giao, GHN có thể báo `delivery_failed`, giao lại về `shipping`, hoặc chuyển `returning → returned`.
- `completed`, `cancelled` và `returned` là trạng thái cuối, không được chuyển ngược thủ công.
- Trạng thái chi tiết của GHN được chuẩn hóa qua state machine vận chuyển (`picking → ready_to_pick`, các trạng thái luân chuyển/giao → `shipping`). Sự kiện lạ hoặc sự kiện cũ làm lùi trạng thái bị bỏ qua và ghi log.
- `partial_return` là trạng thái khóa để đối soát thủ công; webhook GHN đến sau không được tự xóa cảnh báo hoặc tự hoàn tất đơn.
- Thanh toán: `unpaid → pending → paid` hoặc `failed`; giao dịch lỗi có thể tạo lượt thanh toán mới từ `failed → pending`; hủy đơn đã trả tiền dùng `paid → refund_pending → refunded`.
- Mỗi lần thử thanh toán có một dòng riêng trong `payment_transactions`. MoMo cho phép thanh toán lại trên cùng đơn và tạo giao dịch mới; VNPAY hiện hủy đơn và hoàn tồn kho khi IPN báo thất bại nên không mở thanh toán lại trên đơn đã hủy.
- Return URL trên trình duyệt chỉ hiển thị kết quả. Chỉ IPN đã kiểm tra chữ ký, số tiền và mã giao dịch mới được cập nhật thanh toán.
- Nếu gateway gửi thành công muộn cho đơn đã hủy, hệ thống ghi nhận tiền rồi chuyển đơn sang `refund_pending`. Nếu hai lần MoMo cùng thu tiền, giao dịch thứ hai chuyển `refund_pending` để quản trị viên đối soát, còn trạng thái thanh toán chính của đơn vẫn là `paid`.
- `paid → refunded` chỉ xảy ra khi toàn bộ tiền hàng thực trả đã được hoàn. Hoàn một phần vẫn giữ trạng thái `paid` và theo dõi bằng `refunded_amount`.
- Webhook VNPAY/MoMo được xác thực chữ ký, số tiền và mã đơn trước khi thay đổi dữ liệu. Replay cùng sự kiện là no-op thành công; cùng event key nhưng payload khác bị từ chối.

## Tồn kho

- Mỗi thay đổi tồn kho khóa dòng biến thể trong transaction và ghi một `inventory_movement` có idempotency key duy nhất.
- Checkout khóa tồn kho trước khi tạo đơn; token checkout và session lock ngăn double-submit tạo hai đơn.
- Hủy đơn hoàn đúng lượng đã trừ, đúng một lần. Thanh toán MoMo thất bại chưa hoàn kho vì đơn vẫn còn hiệu lực để khách thanh toán lại.
- Đổi size giữ trước biến thể thay thế khi duyệt. Nếu hủy yêu cầu đã duyệt, lượng giữ được trả lại đúng một lần.
- Hàng khách trả chỉ cộng lại kho khi admin xác nhận đã nhận hàng, không cộng lúc khách mới gửi yêu cầu.

## Đổi trả và hoàn tiền

- Chỉ đơn `completed` trong 7 ngày mới được gửi yêu cầu.
- Trạng thái: `requested → approved → received → completed`; có thể `rejected` từ `requested`, hoặc `cancelled` trước khi nhận hàng.
- Tổng số lượng của mọi yêu cầu không bị từ chối/hủy không được vượt số lượng đã mua của từng dòng hàng. Sản phẩm đã xử lý hết không xuất hiện như lựa chọn hợp lệ lần nữa.
- Tiền hoàn sản phẩm được tính theo tỷ lệ giá hàng thực trả sau giảm giá; phí vận chuyển không tự động hoàn.
- Khi hoàn tất, hệ thống khóa cả yêu cầu và đơn, kiểm tra số tiền còn có thể hoàn, rồi ghi `refund_processed_at`. Yêu cầu lặp lại không cộng tiền hoặc kho lần hai.
- Hoàn một phần từ GHN chỉ gắn cờ chờ đối soát. Không tự đổi trạng thái tổng của đơn và không tự cộng toàn bộ hàng vào kho.
- Việc chuyển tiền thực tế qua cổng/ngân hàng phải được đối soát theo mã giao dịch của đơn trước khi admin bấm hoàn tất.

## Quy tắc tích hợp

- Queue gửi email có retry/backoff và chỉ dispatch sau khi transaction commit; lỗi vĩnh viễn được ghi log riêng và phát cảnh báo nếu đã cấu hình webhook cảnh báo. COD, VNPAY và MoMo đều phải tạo đúng một email nhận đơn sau khi tạo đơn thành công.
- COD tạo vận đơn GHN ngay sau khi đơn được commit. VNPAY và MoMo chỉ tạo vận đơn sau khi IPN hợp lệ xác nhận đã thanh toán; khóa phân tán và mã vận đơn trên đơn ngăn tạo trùng.
- Lỗi ngay lúc enqueue cũng phát `mail_enqueue_failed` mà không đưa exception message/secret ra webhook. Sau khi sửa hạ tầng, vận hành có thể gửi lại đúng loại email bằng `mail:resend-business`; không tự động gửi lại mù để tránh email trùng.
- Không đưa token, secret, mật khẩu hoặc payload thanh toán đầy đủ vào log.
- Khi nhận webhook không rõ trạng thái, giữ dữ liệu hiện tại và điều tra log; không tự suy đoán chuyển sang trạng thái cuối.
- Chỉ replay webhook/job sau khi đã xác định lỗi hạ tầng, vì mã nguồn xử lý replay theo idempotency key chứ không theo thao tác thủ công của người vận hành.
