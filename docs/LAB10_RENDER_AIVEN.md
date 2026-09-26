# Lab 10 — Triển khai Vua Beach lên Render và Aiven

## 1. Kiến trúc

- GitHub lưu mã nguồn.
- Render Web Service build `Dockerfile`, chạy Nginx + PHP-FPM + Laravel Scheduler.
- Aiven MySQL lưu dữ liệu và kết nối TLS bằng Secret File `ca.pem`.
- Queue dùng `sync` để không cần tạo Worker Service riêng cho bài lab.

Image dùng PHP 8.4 vì `composer.lock` hiện tại chứa Symfony 8.1 yêu cầu PHP 8.4.1 trở lên; PHP 8.3 trong ví dụ của tài liệu không còn tương thích với bộ dependency đã khóa của dự án.

## 2. Kiểm tra trước khi đẩy mã

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/doboi
composer install
npm ci
npm run build
php artisan test
git status
```

Không commit `.env`, mật khẩu, App Password Gmail, khóa MoMo/VNPAY/GHN, tệp `.pem`, `.key`, `.sql` hoặc CA thật.

## 3. Đưa mã lên GitHub

```bash
git add .gitignore Dockerfile .dockerignore docker docs config/queue.php app/Console/Commands/PreflightCheck.php
git add -u backups
git commit -m "Add Lab 10 Render deployment"
git push origin HEAD
```

Không dùng `git push --force`. Nếu nhánh hiện tại chưa phải `main`, có thể tạo Pull Request để nhập vào `main`.

## 4. Tạo MySQL trên Aiven

Tạo MySQL ở vùng gần Việt Nam, sau đó lưu các giá trị:

- Host
- Port
- Database
- User
- Password
- CA certificate

Không dùng database local `song_xanh_swimwear` làm database production. Dữ liệu local không tự chuyển lên Aiven.

## 5. Tạo Render Web Service

1. Chọn **New > Web Service** và kết nối repository GitHub.
2. Runtime: **Docker**; branch: `main`; region gần Việt Nam.
3. Không nhập Build Command hoặc Start Command vì `Dockerfile` đã xử lý.
4. Health Check Path: `/up`.
5. Copy từng biến trong `docker/render.env.example` vào **Environment** và thay giá trị thật.
6. Tạo **Secret File** `/etc/secrets/ca.pem`, dán CA certificate của Aiven.

Tạo `APP_KEY` một lần ở máy local rồi lưu cố định trên Render:

```bash
php artisan key:generate --show
```

Không tạo lại `APP_KEY` sau mỗi lần deploy vì dữ liệu mã hóa và phiên đăng nhập cũ sẽ hỏng.

## 6. Migration và tài khoản quản trị

Lần deploy đầu:

```dotenv
RUN_MIGRATIONS=true
RUN_SEEDERS=true
ADMIN_INITIAL_PASSWORD=<mat-khau-rieng-toi-thieu-16-ky-tu>
```

Seeder chỉ tạo admin khi database chưa có admin, không ghi đè tài khoản hiện hữu và nạp catalog mẫu theo kiểu chạy lại an toàn. Khi deploy thành công, đổi ngay:

```dotenv
RUN_SEEDERS=false
ADMIN_INITIAL_PASSWORD=
```

Các lần deploy sau có thể giữ `RUN_MIGRATIONS=true`; migration Laravel chỉ chạy phần chưa chạy.

## 7. Callback thanh toán đúng của Vua Beach

Thay `https://your-service.onrender.com` bằng URL Render thật:

```dotenv
MOMO_RETURN_URL=https://your-service.onrender.com/thanh-toan/momo/ket-qua
MOMO_IPN_URL=https://your-service.onrender.com/thanh-toan/momo/ipn
VNPAY_RETURN_URL=https://your-service.onrender.com/thanh-toan/vnpay/ket-qua
VNPAY_IPN_URL=https://your-service.onrender.com/thanh-toan/vnpay/ipn
```

Nếu dùng Sandbox trên môi trường production để trình diễn, đặt `PAYMENT_SANDBOX_MODE=true` và giữ cảnh báo Sandbox trên giao diện.

## 8. Kiểm tra sau deploy

```text
GET /up
GET /
GET /san-pham
GET /dang-nhap
GET /admin
```

Kiểm tra thêm:

- Đăng ký và xác thực email tạo URL Render, không còn localhost/ngrok.
- Đặt đơn COD.
- Thanh toán/cancel MoMo và VNPAY cập nhật đúng lịch sử giao dịch.
- Admin xử lý đơn và xem báo cáo Finance.
- Render Logs không có lỗi migration, CA, Nginx, PHP-FPM hoặc scheduler.

## 9. Ảnh cần chụp nộp Lab 10

1. Repository GitHub có `Dockerfile` và thư mục `docker`.
2. Aiven service ở trạng thái Running (che password).
3. Render Web Service deploy thành công.
4. Render Environment chỉ hiện tên biến, không để lộ giá trị bí mật.
5. Secret File `ca.pem` đã tạo (không chụp nội dung CA).
6. Render Logs có migration và dòng Nginx/PHP-FPM khởi động thành công.
7. `/up` trả HTTP 200.
8. Trang chủ, sản phẩm, đăng nhập và Admin chạy bằng HTTPS trên URL Render.
9. Một đơn hàng thử và báo cáo Finance hoạt động.

## Lưu ý

Filesystem của Web Service có thể bị thay thế khi deploy lại. Ảnh tải lên qua Admin cần Object Storage hoặc Persistent Disk nếu muốn giữ lâu dài; ảnh catalog nằm trong repository vẫn hoạt động bình thường.
