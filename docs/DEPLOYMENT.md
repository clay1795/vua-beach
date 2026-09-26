# Vua Beach: triển khai và cập nhật an toàn

## Nguyên tắc

- Không đưa `.env`, token GHN, VNPAY Hash Secret hoặc mật khẩu SMTP vào Git.
- Mọi xử lý đơn hàng, tồn kho, hoàn tiền và đổi trả tuân theo [BUSINESS_RULES.md](BUSINESS_RULES.md); không sửa trực tiếp trong database.
- Luôn sao lưu database và `storage/app/public` trước migration hay cập nhật code.
- Triển khai lần đầu ở staging trước; Production chỉ dùng khi đã kiểm thử thanh toán/webhook.
- Không chạy `migrate:fresh`, `db:wipe`, `reset --hard` hoặc lệnh xóa dữ liệu trên Production.

Các tích hợp ngoài dùng feature flag độc lập. Đặt `GHN_ENABLED=false`,
`MOMO_ENABLED=false` hoặc `VNPAY_ENABLED=false` khi chưa có credential Live;
checkout vẫn cho phép COD và GHN tắt sẽ dùng `GHN_DEFAULT_FEE`. Health-check ghi
`skipped` cho tích hợp đã tắt có chủ đích, nhưng tích hợp đang bật mà sai
credential hoặc không truy cập được vẫn làm gate thất bại.

## 1. Những thông tin cần có trước khi triển khai

| Hạng mục | Cần cung cấp |
|---|---|
| Domain | Ví dụ `vuabeach.vn`, quyền quản lý DNS |
| Hosting/VPS | SSH hoặc panel, PHP 8.4+, MySQL/MariaDB, cron |
| Email | SMTP tên miền hoặc Gmail App Password |
| VNPAY | TmnCode, Hash Secret, môi trường Sandbox/Production, IPN đã duyệt |
| GHN | Token Production, Shop ID, From District ID |
| Backup | Nơi lưu bản dump database và thư mục upload ảnh |

## 2. Cấu hình Production

1. Tạo file `.env` trên server từ `.env.production.example`.
2. Điền `APP_KEY` bằng `php artisan key:generate` **một lần duy nhất**.
3. Đặt `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://domain-cua-ban`.
4. Chỉ dùng HTTPS ở `APP_URL`, `VNPAY_RETURN_URL`, `VNPAY_IPN_URL`.
5. VNPAY phải được cấu hình IPN là:

   `https://domain-cua-ban/thanh-toan/vnpay/ipn`

6. Tạo `GHN_WEBHOOK_SECRET` ngẫu nhiên tối thiểu 32 byte và đăng ký callback GHN:

   `https://domain-cua-ban/van-chuyen/ghn/webhook?secret=<GHN_WEBHOOK_SECRET>`

   Cấu hình reverse proxy chỉ ghi `$uri`, không ghi query string của route này. Nếu hạ tầng GHN hỗ trợ custom header, ưu tiên gửi secret qua `X-GHN-Webhook-Secret`.
   Giữ `WEBHOOK_MAX_PAYLOAD_BYTES=65536`; middleware sẽ trả HTTP 413 trước controller nếu body hoặc query string vượt giới hạn.

7. Kiểm tra quyền ghi cho `storage` và `bootstrap/cache` của user web server.
   Thư mục log nên là `0750`, file log là `0640`; cấu hình launchd/Supervisor kèm theo đã đặt umask `027`.

## 3. Quy trình triển khai lần đầu

1. Tạo và gắn **Static IPv4** Lightsail vào instance trước, rồi trỏ DNS domain
   về đúng Static IP và cài chứng chỉ TLS/HTTPS. Không trỏ DNS lâu dài vào IPv4
   động mặc định vì địa chỉ đó có thể đổi sau chu kỳ Stop/Start. Trong tab
   Networking của instance, biểu tượng ghim màu xanh cạnh Public IPv4 là dấu
   hiệu AWS xác nhận địa chỉ đã static.
2. Đặt DocumentRoot/Nginx root vào thư mục `public/`, không trỏ vào thư mục gốc Laravel.
   Có thể bắt đầu từ [`deploy/nginx/vua-beach.conf.template`](../deploy/nginx/vua-beach.conf.template): thay domain, đường dẫn project, socket PHP-FPM và chứng chỉ, sau đó chạy `sudo nginx -t` trước khi reload. Mẫu này nén CSS/JS/SVG, cache asset Vite có hash trong một năm với `immutable`, cache ảnh/font trong 30 ngày, chỉ cho phép `index.php` đi qua PHP-FPM và chặn dotfile.
   `APP_URL` cũng là host duy nhất được ứng dụng tin cậy trên staging/production, vì vậy phải ghi đúng domain public. Nếu TLS kết thúc ở reverse proxy, đặt `TRUSTED_PROXIES` thành đúng IP/CIDR của proxy (ví dụ `127.0.0.1,::1` khi proxy cùng máy). Không dùng `*` hoặc `**`: client có thể giả `X-Forwarded-Proto`/`X-Forwarded-For`. Cài `deploy/nginx/vua-beach-log-format.conf` để access log bỏ query/referrer và che token reset/hash xác thực trong path. Kiểm tra response HTTPS có `Strict-Transport-Security` và cookie phiên có cờ `Secure`.
3. Tải source code, chạy `composer install --no-dev --prefer-dist --optimize-autoloader`.
   Giữ `SENTRY_SEND_DEFAULT_PII=false` và `SENTRY_MAX_REQUEST_BODY_SIZE=never`; ứng dụng còn scrub token reset, webhook secret và chữ ký thanh toán khỏi URL/query/header trước khi gửi event.
4. Điền `.env`, chạy `php artisan migrate --force` và `php artisan storage:link`.
5. Chạy `EXPECTED_APP_ENV=production SUPERVISOR_SCHEDULER_PROGRAM=vua-beach-scheduler bash scripts/deploy-release.sh` từ thư mục project. Script sẽ từ chối nếu môi trường thực tế không khớp; tên chương trình Supervisor chỉ được chứa chữ, số, `_` và `-`.
6. Thiết lập cron mỗi phút:

   `* * * * * cd /path/to/doboi && php artisan schedule:run >> /dev/null 2>&1`

7. Thiết lập queue worker bằng Supervisor hoặc systemd:

   `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
   Sau khi triển khai phiên bản mới, chạy `php artisan queue:restart` để worker nạp mã mới.
   Trên Ubuntu có thể dùng `deploy/supervisor/supervisord.conf` làm cấu hình gốc
   để giữ `umask=027`; các log worker/scheduler sau rotate không được đọc bởi
   user ngoài owner/group.
   Giữ `QUEUE_AFTER_COMMIT=true`; preflight strict sẽ từ chối phát hành nếu queue
   có thể chạy email/job trước khi transaction database commit.

   Trên VPS public, chỉ mở `22`, `80`, `443`; MySQL phải bind vào loopback. SSH
   dùng public key, tắt password/root login và bật fail2ban. Có thể chép
   `deploy/fail2ban/vua-beach.local` vào `/etc/fail2ban/jail.d/`, sau đó kiểm tra
   bằng `sudo fail2ban-client status sshd`. Không giới hạn SSH theo một IP cố định
   nếu đường truyền quản trị dùng IP động và chưa có kênh truy cập dự phòng.

8. Test một đơn COD và một đơn VNPAY Sandbox trước khi bật key Production.

### Cổng staging bắt buộc

1. Tạo database và domain staging tách biệt, sao chép `.env.staging.example` thành `.env` trên máy staging rồi thay toàn bộ giá trị mẫu. `STAGING_DATABASE_GUARD` phải khớp chính xác tên database thực; tên phải chứa `staging`, `stage`, `uat` hoặc `preprod`. Gate xác minh trực tiếp trên connection và dừng trước mọi bài test ghi dữ liệu nếu không khớp.
2. Không dùng chung database, bucket upload, SMTP người gửi hoặc khóa thanh toán Production.
3. Máy chạy gate phải có PHP, Composer, curl và Node/npm đúng mức tối thiểu trong `package.json`; cài Composer **có development dependencies** vì PHPUnit và lệnh `artisan test` là bắt buộc. Chạy `npm ci && npm run build`, `composer install`, sau đó `npm run test:js`, `php artisan test --compact` và `npm run test:e2e` trong CI hoặc máy build.
4. Gate chạy bài tranh chấp thật `inventory:concurrency-test --workers=20 --stock=5`, tạo backup MySQL rồi phục hồi vào database tạm bằng `db:restore-test`. Chỉ triển khai Production khi cả ba bước và `php artisan vua-beach:preflight --strict` trả mã 0.
5. Kiểm thử Sandbox: thanh toán thành công/thất bại, IPN lặp lại/sai chữ ký, GHN, email, một vòng đổi trả và mobile.
6. Máy chạy gate phải có PHP, Composer, Node/npm, curl và Composer development dependencies (để có PHPUnit/Artisan `test`). Khi staging đã có domain HTTPS và `.env` riêng, chạy `bash scripts/staging-gate.sh`; script từ chối sớm nếu thiếu runtime, nhận nhầm môi trường/database, đồng thời xác minh HSTS, cờ `Secure` trên toàn bộ cookie trang đăng nhập, gzip và cache `immutable` của asset Vite có fingerprint. Nếu staging được bảo vệ bằng Basic Auth, tạo một file netrc chỉ đọc bởi user triển khai (mode `600`, không commit), rồi chạy `STAGING_CURL_NETRC=/đường/dẫn/tuyệt/đối/staging.netrc bash scripts/staging-gate.sh`. Không truyền mật khẩu trực tiếp bằng `curl -u` vì có thể lộ qua lịch sử shell hoặc danh sách tiến trình.
7. `/health` chỉ trả `ok` khi queue worker và scheduler đã tự ghi heartbeat trong vòng 120 giây, SMTP/GHN hoạt động và các cổng thanh toán **đang bật** phản hồi qua HTTPS. Cổng thanh toán chủ động tắt được báo `skipped`, không làm server thành `degraded`; cổng đã bật nhưng lỗi vẫn làm gate thất bại. Gate chờ có giới hạn tối đa hai phút sau khi restart để các daemon ghi heartbeat mới; không bỏ qua trạng thái `degraded`.
8. Cổng staging tự gửi cảnh báo thử qua webhook HTTPS và/hoặc email đồng bộ. Nếu có Sentry DSN, gate gửi event Sentry; nếu chưa có, gate tạo một incident nội bộ đã khử dữ liệu nhạy cảm, gửi cảnh báo rồi đóng incident thử. Phải thấy lệnh probe thành công trước khi duyệt phát hành.

## 4. Checklist trước Go-live

- [ ] `APP_DEBUG=false`; trang lỗi không lộ stack trace.
- [ ] Lightsail đã gắn Static IPv4; bản ghi `@`, `www`, `staging` cùng trỏ đúng
      địa chỉ đó (đối chiếu bằng `dig +short A <domain>`).
- [ ] `TRUSTED_PROXIES` chỉ chứa proxy do mình kiểm soát; HTTPS có HSTS và session cookie `Secure`.
- [ ] `SESSION_DRIVER=database`; đổi thử mật khẩu và xác nhận các phiên đăng nhập trên thiết bị khác bị thu hồi, còn phiên hiện tại vẫn hoạt động.
- [ ] VNPAY IPN trả HTTP 200 và cập nhật đơn đúng số tiền.
- [ ] Hủy thử một đơn online đã thanh toán: đơn chuyển `cancelled`, thanh toán
      chuyển `refund_pending`, kho chỉ hoàn một lần; sau khi hoàn tiền thật mới
      bấm xác nhận và kiểm tra trạng thái `refunded`.
- [ ] GHN tạo vận đơn, có mã vận đơn và đồng bộ trạng thái.
- [ ] GHN webhook đúng secret trả 200; replay không tạo thêm lịch sử/trừ hoặc hoàn kho lần hai.
- [ ] Gửi thử callback `IsPartialReturn=true`; dashboard phải báo chờ đối soát và chưa tự cộng tồn kho.
- [ ] SMTP gửi email xác thực, đặt hàng, đổi trạng thái và đặt lại mật khẩu; kiểm tra link reset chỉ dùng được một lần.
- [ ] `QUEUE_AFTER_COMMIT=true`; thử cả COD, VNPAY và MoMo đều tạo đúng một email nhận đơn.
- [ ] Upload JPG/PNG/WebP hiển thị qua HTTPS sau khi chạy `storage:link`; ảnh sản phẩm, danh mục và logo đều được giải mã rồi tái mã hóa WebP, cạnh dài không quá 1800px, tổng ảnh nguồn không quá 12 megapixel. File giả MIME/polyglot không bao giờ được lưu nguyên bản.
- [ ] Cả khách và admin không truy cập được route trái quyền.
- [ ] Backup database và upload đã được thử khôi phục.
- [ ] Kiểm tra mobile Safari/Chrome, checkout, địa chỉ và giỏ hàng.

## 5. Quy trình cập nhật sau này

1. Trước khi ghi đè source hiện tại, chạy `RELEASE_SNAPSHOT_DIR=/var/backups/vua-beach/releases bash scripts/release-snapshot.sh`, rồi chạy `bash scripts/verify-release-snapshot.sh /đường/dẫn/release-<timestamp>.tar.gz`. Snapshot code không chứa `.env`, upload, log, `vendor` hoặc `node_modules`; giữ `.tar.gz` và `.sha256` cùng nhau với quyền `600`. Sau đó sao lưu database và `storage/app/public`.
2. Cập nhật code ở staging; trước khi chạy test luôn dùng `php artisan optimize:clear`, sau đó chạy `npm run test:js`, `php artisan test --compact` và kiểm tra migration. Không chạy test khi config Production đang cache vì test có thể nhận nhầm cấu hình môi trường.
3. Cập nhật Production ở thời điểm ít đơn hàng.
4. Chạy `EXPECTED_APP_ENV=production SUPERVISOR_SCHEDULER_PROGRAM=vua-beach-scheduler bash scripts/deploy-release.sh`.
5. Kiểm tra trang chủ, admin, giỏ hàng, đặt đơn và log lỗi.
6. Nếu lỗi, rollback code về release trước; **không rollback database nếu migration có dữ liệu mới**. Cần migration phục hồi riêng đã được kiểm thử.

Trên VPS, user triển khai và PHP-FPM/queue phải cùng đọc được `.env`, nhưng user khác không được đọc bí mật. Với cấu hình chuẩn `ubuntu` + `www-data`:

```bash
sudo chown ubuntu:www-data .env
sudo chmod 640 .env
sudo chown -R ubuntu:www-data bootstrap/cache
sudo chmod 2770 bootstrap/cache
sudo chmod 640 bootstrap/cache/config.php
```

Không chạy riêng `php artisan optimize:clear` trên Production rồi bỏ đó: lệnh này xóa cache cấu hình đang phục vụ web và worker. Luôn cache lại ngay bằng `config:cache`, `route:cache`, `view:cache`, hoặc dùng `scripts/deploy-release.sh`. Script release lưu tạm cache cấu hình cũ với quyền `600`; nếu bước phát hành lỗi trong maintenance, nó khôi phục cache cũ trước khi mở website.

`scripts/deploy-release.sh` tự kiểm tra đúng môi trường, chạy preflight, bật maintenance trước khi chụp backup nhất quán của MySQL và `storage/app/public`, cài dependencies, migrate, cache cấu hình, kiểm tra strict rồi restart queue. Probe incident chạy dưới `APP_RUNTIME_USER` (mặc định `www-data`) để dùng đúng quyền log runtime mà không phải nới mode `0640`. Sau khi mở site, script **luôn** smoke-test HTTPS tại `APP_URL`, xác nhận header HSTS và kiểm tra mọi cookie ở trang đăng nhập đều có cờ `Secure`; nếu truyền `RELEASE_HEALTH_URL` thì giá trị phải khớp chính xác `APP_URL` để tránh kiểm tra nhầm website. Callback VNPAY/MoMo trên staging/production cũng phải cùng origin với `APP_URL`:

Preflight đầu tiên chạy ở chế độ strict **trước khi** bật maintenance hoặc migrate. Tiếp theo script gửi cảnh báo thử; nếu có Sentry DSN thì gửi Sentry event, nếu không thì kiểm tra tracker incident nội bộ và kênh cảnh báo. Bất kỳ bước nào lỗi đều dừng phát hành trước khi thay đổi dữ liệu. Production từ chối MoMo/VNPAY nếu vẫn trỏ tới host sandbox, trừ giai đoạn pre-launch đã chủ động đặt `PAYMENT_SANDBOX_MODE=true`. Ngoại lệ này hiển thị cảnh báo Sandbox trên toàn storefront và trong từng lựa chọn thanh toán; nó chỉ dùng để kiểm thử, không phải trạng thái đủ điều kiện go-live. Trước khi nhận đơn thật, bắt buộc đổi endpoint/credential Production, đặt `PAYMENT_SANDBOX_MODE=false`, cache lại cấu hình và chạy lại preflight strict.

```bash
EXPECTED_APP_ENV=production RELEASE_HEALTH_URL=https://vuabeach.vn bash scripts/deploy-release.sh
```

Nếu server chịu trách nhiệm build frontend thay vì nhận sẵn thư mục `public/build`, dùng:

```bash
EXPECTED_APP_ENV=production BUILD_FRONTEND=true RELEASE_HEALTH_URL=https://vuabeach.vn bash scripts/deploy-release.sh
```

## 6. Runbook rollback

1. Ghi nhận mã release lỗi, thời điểm, health response và lỗi Sentry; bật maintenance: `php artisan down --retry=60`.
2. Xác minh snapshot bằng `scripts/verify-release-snapshot.sh`. Giải nén snapshot code đã chạy tốt vào một thư mục release **mới**, liên kết `.env` và `storage` dùng chung, chạy `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`, sau đó mới trỏ symlink hiện hành về release đó. Không giải nén chồng lên thư mục đang phục vụ request.
3. Không chạy `migrate:rollback` tự động. Nếu migration mới tương thích ngược, giữ nguyên schema. Nếu không tương thích, dùng migration phục hồi đã được duyệt.
4. Khôi phục upload từ `storage/app/backups/uploads-<timestamp>.tar.gz` chỉ khi release lỗi đã thay đổi/mất file; không ghi đè ảnh mới nếu chưa đối soát.
5. Khôi phục database Production chỉ trong sự cố mất/hỏng dữ liệu và sau khi đã thử file trên database tạm:

```bash
php artisan db:restore-test --file=storage/app/backups/database-<timestamp>.sql
```

6. Chạy `php artisan optimize:clear`, cache lại config/route/view, `php artisan queue:restart`, rồi `php artisan vua-beach:preflight --strict`.
7. Mở site bằng `php artisan up`; kiểm tra `/health`, đăng nhập, giỏ hàng và một smoke order không thanh toán thật.
8. Ghi lại nguyên nhân, dữ liệu bị ảnh hưởng và biện pháp ngăn tái diễn. Không xóa backup/sự kiện webhook phục vụ điều tra.

## 7. Kiểm tra sau triển khai

- Log: `storage/logs/laravel.log`.
- Scheduler: `php artisan schedule:list`.
- Queue lỗi: bảng `failed_jobs` hoặc `php artisan queue:failed`.
- Nếu enqueue mail nghiệp vụ từng thất bại và alert ghi lại ID/mã đơn, sau khi
  sửa hạ tầng dùng `php artisan mail:resend-business order-placed VB-...`,
  `order-status <order-id>` hoặc `return-status <return-id>`. Đây là thao tác
  gửi lại có chủ đích; kiểm tra người nhận và log trước khi chạy để tránh gửi lặp.
- GHN: `php artisan ghn:sync-orders --limit=50`.
- GHN hoàn một phần: xử lý các đơn có nhãn “Hoàn một phần — chờ đối soát” trên dashboard; chỉ cập nhật kho sau khi xác nhận đúng từng sản phẩm thực nhận.
- Webhook bị kẹt ở `processing` chỉ được nhận lại sau `WEBHOOK_PROCESSING_TIMEOUT_SECONDS` (mặc định 300 giây); không hạ ngưỡng dưới 30 giây.
- Cache sau đổi `.env`: `php artisan optimize:clear && php artisan config:cache`.
- Kiểm tra không thay đổi dữ liệu: `php artisan vua-beach:preflight --strict`.
- Kiểm tra đường cảnh báo bằng `php artisan vua-beach:alert-test`; sau đó chạy `php artisan sentry:test` nếu có DSN, hoặc `php artisan vua-beach:incident-test` nếu dùng tracker nội bộ. Xác nhận probe thành công trước khi mở site.
- Chạy restore drill định kỳ: `php artisan db:restore-test`.
- Báo cáo E2E HTML: `output/playwright/report/index.html` sau `npm run test:e2e`.
