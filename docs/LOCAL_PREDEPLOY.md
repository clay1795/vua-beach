# Task Local trước triển khai: Vua Beach

Mục tiêu: website có thể chạy ổn định ở local trước khi cấu hình hạ tầng internet. Không reset database, không tạo dữ liệu bằng code cứng và không dùng key Production.

## Quy tắc an toàn

1. Trước bất kỳ thay đổi database nào, xuất backup từ phpMyAdmin hoặc `mysqldump`.
2. Không dùng `migrate:fresh`, `db:wipe` hay `migrate:rollback` trên database đang có dữ liệu.
3. Test tự động đã tách SQLite in-memory. Trước mỗi lần test: `php artisan optimize:clear`.
4. Dữ liệu danh mục/sản phẩm/biến thể phải được thêm từ Admin để lưu vào database.

## A. Nền tảng và dữ liệu

- [x] Laravel/PHP/Composer hoạt động.
- [x] Migration đã chạy đầy đủ.
- [x] Storage public đã liên kết.
- [x] Queue tables, scheduler GHN và route hợp lệ.
- [x] Composer không có advisory bảo mật.
- [ ] **Bắt buộc:** thêm tối thiểu 3 danh mục và 6 sản phẩm qua Admin.
- [ ] Mỗi sản phẩm cần ít nhất 1 biến thể: màu, size, SKU, giá, tồn kho và ảnh local đọc được.
- [ ] Không để sản phẩm test/demo ở trạng thái đang bán. Preflight sẽ chặn cả dữ liệu thử và ảnh placeholder/hỏng.
- [ ] Tạo một địa chỉ giao hàng mặc định có đủ tỉnh/quận/phường GHN.

Lệnh đọc trạng thái:

```bash
php artisan vua-beach:preflight
```

## B. Luồng khách hàng

- [ ] Đăng ký bằng email thật và nhận link xác thực.
- [ ] Chưa xác thực không thêm giỏ/không xem lịch sử đơn.
- [ ] Đăng nhập, cập nhật thông tin cá nhân.
- [ ] Thêm, sửa, xóa, đặt địa chỉ mặc định.
- [ ] Thêm sản phẩm, đổi số lượng, checkbox và tổng tiền cập nhật tức thì.
- [ ] Checkout COD với phí GHN thật.
- [ ] Đặt đơn COD, kiểm tra email chi tiết đơn và ảnh sản phẩm trong lịch sử đơn.
- [ ] Hủy đơn, xác minh hoàn tồn kho đúng một lần.

## C. VNPAY Sandbox

- [ ] Điền `VNPAY_TMN_CODE`, `VNPAY_HASH_SECRET`, Return URL và IPN URL vào `.env`.
- [ ] Đặt `VNPAY_ENABLED=true`.
- [ ] Dùng HTTPS tunnel cho local để VNPAY gọi IPN.
- [ ] Đặt đơn VNPAY Sandbox, thanh toán thử, kiểm tra `payment_status=paid`.
- [ ] Kiểm tra mã giao dịch, số tiền, email trạng thái và xử lý giao dịch bị hủy.
- [ ] Chạy `php artisan config:clear` sau khi sửa `.env`.

## D. GHN

- [ ] Dropdown tỉnh/quận/phường tải được.
- [ ] Phí ship thay đổi theo địa chỉ.
- [ ] Admin xác nhận đơn, tạo được mã vận đơn.
- [ ] Đồng bộ lại bằng `php artisan ghn:sync-orders --limit=50`.
- [ ] Hủy đơn không tạo nhiều vận đơn hoặc hoàn tồn kho nhiều lần.

## E. Admin và quyền

- [ ] User thường bị chặn tại `/admin`.
- [ ] Admin thêm/sửa/ngừng bán sản phẩm, không xóa lịch sử đơn.
- [ ] Upload JPG/PNG/WebP cho ảnh đại diện/album/logo, kiểm tra ảnh hiển thị lại sau tải trang và file lưu mới có đuôi WebP. Ảnh nguồn vượt 12 megapixel hoặc file giả ảnh phải bị từ chối bằng lỗi tiếng Việt.
- [ ] Kiểm tra cảnh báo sắp hết hàng, lịch sử nhập/xuất.
- [ ] Lọc đơn, xuất CSV, đồng bộ GHN và đổi trạng thái.
- [ ] Thử thay đổi role; không thể xóa/hạ quyền admin cuối cùng.

## F. Giao diện và lỗi

- [x] Trang chủ desktop đã duyệt: header, empty state, footer hiển thị ổn.
- [x] Duyệt mobile Safari/Chrome với catalog thật; bộ lọc thu gọn và sản phẩm xuất hiện sớm trong màn hình đầu.
- [ ] Kiểm tra ảnh vỡ, chữ tràn, form lỗi, trạng thái giỏ trống.
- [ ] Duyệt `storage/logs/laravel.log`; chỉ chấp nhận lỗi đã biết và đã xử lý.

## G. Điều kiện chuyển sang triển khai

Chỉ bắt đầu staging/Production sau khi toàn bộ ô bắt buộc ở A, B, C và D đạt. Khi đó làm theo `docs/DEPLOYMENT.md`.
