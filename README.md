# Vua Beach

Website bán đồ bơi xây dựng bằng Laravel, gồm storefront cho khách và khu vực quản trị riêng.

## Các chức năng đã có

- Danh mục, sản phẩm, biến thể màu/size, SKU, ảnh đại diện và thư viện ảnh.
- Tồn kho theo biến thể, cảnh báo mức tồn thấp và lịch sử nhập/xuất phát sinh từ quản trị hoặc đơn hàng.
- Giỏ hàng chọn nhiều sản phẩm, thay đổi số lượng và tổng tiền tự cập nhật.
- Checkout COD/MoMo giao diện, sổ địa chỉ thêm/sửa/xóa/đặt mặc định và chọn Tỉnh/Quận/Phường từ GHN.
- Đơn hàng của khách tách riêng; mỗi dòng đơn lưu ảnh sản phẩm tại thời điểm mua.
- Email chi tiết khi đặt hàng, đổi trạng thái đơn và cập nhật đổi trả; gửi qua hàng đợi database để không làm chậm website.
- Yêu thích sản phẩm và đánh giá chỉ dành cho khách đã mua đơn hoàn thành.
- Quản trị sản phẩm, danh mục, đơn hàng, tài khoản/role, cài đặt thương hiệu, nhật ký quản trị, lọc và xuất CSV mở bằng Excel.
- GHN: tính phí thật, tạo vận đơn khi xác nhận và đồng bộ trạng thái vận đơn.
- SEO cơ bản: canonical, Open Graph, `robots.txt` và `sitemap.xml`.

## Chạy tại máy Mac với XAMPP

Sau khi mở MySQL trong XAMPP, có thể chạy đầy đủ web, queue, log và Vite trong một Terminal:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/doboi
composer run dev
```

Hoặc chạy từng tiến trình riêng:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/doboi
php artisan migrate
php artisan storage:link
php artisan serve
```

Mở `http://127.0.0.1:8000`.

## Cấu hình bắt buộc trong `.env`

Không đưa file `.env` hoặc token lên Git.

```dotenv
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_DATABASE=song_xanh_swimwear

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=dia-chi-gmail-cua-ban
MAIL_PASSWORD=app-password-gmail
MAIL_FROM_ADDRESS=dia-chi-gmail-cua-ban
MAIL_FROM_NAME="Vua Beach"

GHN_BASE_URL=https://dev-online-gateway.ghn.vn/shiip/public-api
GHN_TOKEN=token-ghn-cua-ban
GHN_SHOP_ID=shop-id-cua-ban
GHN_FROM_DISTRICT_ID=ma-quan-lay-hang
GHN_WEBHOOK_SECRET=chuoi-ngau-nhien-toi-thieu-32-byte
WEBHOOK_MAX_PAYLOAD_BYTES=65536
WEBHOOK_PROCESSING_TIMEOUT_SECONDS=300
```

Sau khi sửa `.env`, chạy:

```bash
php artisan optimize:clear
```

Để email thực sự được gửi trong local, mở một Terminal thứ hai và để worker chạy:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/doboi
php artisan queue:work --tries=3
```

Để thử lịch tự động (đồng bộ GHN mỗi 15 phút và backup lúc 02:00), chạy:

```bash
php artisan schedule:work
```

## Kiểm tra trước khi nộp hoặc triển khai

```bash
php artisan test
php artisan view:cache
```

Sao lưu database trước khi triển khai. Với XAMPP trên Mac, đặt đường dẫn binary một lần cho Terminal hiện tại rồi chạy lệnh backup của dự án:

```bash
export MYSQLDUMP_BINARY=/Applications/XAMPP/xamppfiles/bin/mysqldump
php artisan db:backup
```

File được lưu tại `storage/app/backups/`, chỉ tài khoản hệ điều hành hiện tại có quyền đọc. Scheduler giữ 14 bản gần nhất; có thể đổi bằng `DB_BACKUP_KEEP`.

## Khi đưa lên hosting

1. Đặt `APP_ENV=production`, `APP_DEBUG=false` và `APP_URL=https://ten-mien-cua-ban`.
2. Cấu hình HTTPS; web server phải trỏ vào thư mục `public/`.
   Nếu dùng reverse proxy, đặt `TRUSTED_PROXIES` đúng IP/CIDR của proxy; không dùng wildcard `*`/`**`.
3. Chạy `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`.
4. Thiết lập worker queue bền vững bằng Supervisor/systemd: `php artisan queue:work --tries=3 --max-time=3600`.
5. Chạy scheduler bằng cron mỗi phút: `* * * * * php /duong-dan-toi/artisan schedule:run >> /dev/null 2>&1`. Scheduler tự đồng bộ GHN mỗi 15 phút và backup database lúc 02:00.
   Queue worker và scheduler đồng thời ghi heartbeat; `/health` sẽ trả HTTP 503 nếu một trong hai không chạy trong quá 120 giây.
6. Đặt `MYSQLDUMP_BINARY` đúng đường dẫn `mysqldump` của hosting; kiểm tra một lần bằng `php artisan db:backup`.
7. Theo dõi `failed_jobs`, log và mục **Nhật ký quản trị** sau mỗi lần cập nhật. Scheduler giữ failed job trong 7 ngày để điều tra rồi tự dọn.

## VNPAY Sandbox / Production

VNPAY đã có luồng tạo URL thanh toán, Return URL và IPN có kiểm tra chữ ký. Nếu giao dịch thất bại/hủy, đơn sẽ được hủy và tồn kho giữ cho đơn được hoàn tự động một lần. Để kích hoạt, cần VNPAY cung cấp `TMN_CODE`, `HASH_SECRET` và domain HTTPS công khai. Điền vào `.env`:

```dotenv
VNPAY_ENABLED=true
VNPAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_TMN_CODE=ma_website_do_vnpay_cap
VNPAY_HASH_SECRET=chuoi_bi_mat_do_vnpay_cap
VNPAY_RETURN_URL=http://localhost:8000/thanh-toan/vnpay/ket-qua
VNPAY_IPN_URL=https://ten-subdomain.ngrok-free.app/thanh-toan/vnpay/ipn
```

Trên local, Return URL tự dùng origin mà khách đang mở: vào bằng `localhost` thì quay về localhost; vào bằng tunnel có hậu tố trong `LOCAL_TUNNEL_HOST_SUFFIXES` (mặc định `.ngrok-free.dev,.ngrok-free.app`) thì quay về chính tunnel hiện tại, kể cả khi tên ngrok vừa đổi. Link xác thực email và đặt lại mật khẩu cũng ghi nhớ origin tại lúc gửi trước khi vào queue. IPN vẫn phải cập nhật thành URL HTTPS công khai đang chạy để máy chủ VNPAY gọi được. Production/Staging luôn dùng URL cố định cùng origin với `APP_URL`; Host lạ không thể được đưa vào email hoặc callback. Sau đó chạy `php artisan optimize:clear` và `php artisan vua-beach:preflight`. Tạo một đơn Sandbox mới và kiểm tra: tiền/transaction number cập nhật qua IPN, email được thêm vào queue, tồn kho chỉ bị xử lý một lần.

## MoMo Sandbox

MoMo dùng IPN POST đã ký HMAC SHA-256 để xác nhận thanh toán; Return URL chỉ thông báo kết quả cho khách. Không đưa khóa merchant vào Git hay chat. Khai báo bộ Sandbox do MoMo cung cấp trong `.env`:

```dotenv
MOMO_ENABLED=true
MOMO_ENDPOINT=https://test-payment.momo.vn/v2/gateway/api/create
MOMO_PARTNER_CODE=partner_code_sandbox
MOMO_ACCESS_KEY=access_key_sandbox
MOMO_SECRET_KEY=secret_key_sandbox
MOMO_RETURN_URL=http://localhost:8000/thanh-toan/momo/ket-qua
MOMO_IPN_URL=https://ten-subdomain.ngrok-free.app/thanh-toan/momo/ipn
```

Chạy local và tạo tunnel HTTPS (thay `http://localhost` bằng cổng XAMPP của bạn nếu khác):

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/doboi
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8000
ngrok http 8000
```

Điền Return URL localhost và IPN URL ngrok như trên, chạy lại `php artisan optimize:clear`, sau đó tạo giao dịch Sandbox mới. Nếu truy cập cửa hàng bằng chính domain ngrok của IPN, Return URL sẽ tự chuyển sang domain đó. Nếu chưa điền đủ cấu hình, MoMo bị khóa trên checkout; COD và VNPAY không bị ảnh hưởng. Khi MoMo từ chối tạo giao dịch hoặc IPN báo thất bại, đơn chờ bị hủy và tồn kho được hoàn đúng một lần.
