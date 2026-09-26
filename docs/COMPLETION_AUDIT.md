# Kiểm toán mức độ hoàn thành Vua Beach

Cập nhật: 12-09-2026. Tài liệu này phân biệt rõ bằng chứng đã chạy với cấu hình chỉ mới sẵn sàng để triển khai.

## Quy ước

- **Đạt local**: code và test đã chạy thành công trên MySQL/local hiện tại.
- **Có gate**: đã có cấu hình và lệnh bắt buộc, nhưng chỉ được chứng minh khi chạy trên staging/production thật.
- **Chờ hạ tầng**: cần domain, TLS, tài khoản hoặc endpoint bên ngoài; không được xem là hoàn thành chỉ nhờ mock.

## 1. Logic nghiệp vụ

| Yêu cầu | Trạng thái | Bằng chứng chính |
|---|---|---|
| State machine đơn hàng và thanh toán | Đạt local | `OrderStateMachine`; `OrderStateMachineTest` duyệt toàn bộ cạnh hợp lệ và từ chối mọi cạnh ngoài graph. |
| State machine GHN/vận chuyển | Đạt local | `ShippingStateMachine`; `ShippingStateMachineTest`; `GhnWebhookTest`. |
| State machine đổi trả | Đạt local | `ReturnStateMachine`; `ReturnStateMachineTest`; luồng hoàn/đổi trong `AdminAndSafetyTest`. |
| Webhook thanh toán/GHN idempotent | Đạt local | `WebhookIdempotencyTest`, `WebhookRequestSecurityTest`, `MomoPaymentTest`, `GhnWebhookTest`; fingerprint canonical hóa đệ quy object lồng nhau nhưng vẫn giữ thứ tự list để replay tương đương không bị xung đột giả. |
| Khóa tồn kho và không oversell | Đạt local | `InventoryService::adjust()` dùng transaction + `lockForUpdate`; drill thật 20 worker/5 tồn cho kết quả 5 thành công, 15 bị từ chối, tồn bằng 0. |
| Hoàn kho/hoàn tiền đúng một lần | Đạt local | idempotency key của `inventory_movements`; test retry hủy đơn, hoàn hàng, partial/full refund. |
| Hủy, giao thất bại, hoàn hàng, đổi một phần | Đạt local | Luật chuẩn tại `BUSINESS_RULES.md`, state graph và test feature/E2E tương ứng. |

## 2. Độ ổn định

| Yêu cầu | Trạng thái | Bằng chứng chính |
|---|---|---|
| Queue/scheduler tự khởi động lại | Đạt VPS | Reboot drill ngày 07-09-2026: SSH hồi phục sau khoảng 20 giây; Supervisor tự bật 2 worker Production, 1 worker Staging và 2 scheduler. `/health` xác nhận heartbeat queue/scheduler mới đều xanh sau boot. |
| Mail retry/backoff/cảnh báo | Đạt Staging | Tất cả mail/notification business dùng queue, `afterCommit`, 5 lần thử và backoff; cảnh báo cuối vòng retry dùng email đồng bộ để không tự tạo vòng lặp queue. Gate ngày 12-09-2026 đã gửi email cảnh báo thật thành công. |
| Log riêng và redaction | Đạt local + VPS | Các channel `payment`, `ghn`, `mail`, `inventory`; `RedactSensitiveDataTest`. Nginx không ghi query/referrer, che token reset và hash xác thực trong path; probe bằng secret giả xác nhận secret không xuất hiện trong access log. |
| Theo dõi exception | Đạt Staging | Hỗ trợ Sentry nếu có DSN; khi chưa có DSN, tracker nội bộ ghi fingerprint, số lần/tình trạng, không lưu message/stack nhạy cảm và gửi cảnh báo có giới hạn. Gate đã tạo, gửi cảnh báo và đóng incident thử thành công. |
| Health database/cache/queue/scheduler/dịch vụ ngoài | Đạt Staging + Production | GHN/MoMo/VNPAY có feature flag: tắt có chủ đích được báo `skipped`; bật nhưng cấu hình/liveness lỗi vẫn làm health thất bại. Staging xác nhận đủ 8/8 mục. Production ngày 12-09-2026 trả `status=ok`; ba tích hợp đang tắt an toàn, COD dùng phí mặc định. |
| Backup và phục hồi | Đạt VPS | Staging và Production đều tạo/restore backup thật. Sau release, Production tạo `database-20260912-215302.sql`, phục hồi đủ 35 bảng vào database tạm rồi dọn sạch. |

## 3. Bảo mật

| Yêu cầu | Trạng thái | Bằng chứng chính |
|---|---|---|
| Không còn mật khẩu admin mặc định/phổ biến | Đạt local | Preflight kiểm tra danh sách mật khẩu mặc định; lệnh `admin:rotate-default-password`; mật khẩu hiện tại đã qua strict gate. |
| HTTPS và Secure cookie | Đạt Production | `tuphim.online` và `www` dùng Let's Encrypt, HTTP/www chuyển về HTTPS apex; HSTS một năm và toàn bộ cookie đăng nhập có cờ `Secure`. Certbot timer và renewal dry-run đã đạt. |
| Production tắt debug | Đạt Production | `APP_DEBUG=false`; preflight strict và deploy script từ chối cấu hình public sai. |
| Rate limit auth/resend/webhook | Đạt local | Named limiter và `RouteSecurityConfigurationTest`, `AuthenticationRateLimitTest`. |
| Mật khẩu mạnh và 2FA admin | Đạt Production | Luật 12 ký tự/mixed/numbers/symbols; admin Production đã đổi mật khẩu mạnh, xác thực email và bật TOTP; preflight public kiểm tra mọi admin. |
| Phân quyền mọi route admin | Đạt local | `RouteSecurityConfigurationTest` duyệt toàn bộ route `admin/*` và bắt buộc `auth`, `verified`, `admin`, `admin.2fa`. |
| Không log bí mật/thẻ/token | Đạt local | Log redactor và Sentry scrubber có test cho context, URL, header, card data và request body. |
| Dependency và upload | Đạt local | `composer audit` không advisory; `npm audit --omit=dev` không vulnerability; test ảnh giả MIME/polyglot/decompression bomb/path escape. |

## 4. Thiết kế và trải nghiệm

Tất cả yêu cầu local đã đạt: loading/double-submit, success/error/empty state, validation tiếng Việt tại đúng field, timeline đơn/đổi trả, trạng thái email và resend cooldown, keyboard/focus/screen reader, WCAG AA light/dark, WebP/AVIF/lazy-load và ảnh local. Bộ E2E chạy trên Chromium, WebKit/Safari, iPhone và Android. Lighthouse ngày 07-09-2026 qua HTTPS Production: mobile Performance 98, Accessibility 100, Best Practices 100, SEO 100; desktop đạt 100 ở cả bốn nhóm. Mobile LCP 2,4 giây, TBT 0 ms, CLS 0,019; desktop LCP 0,6 giây, TBT 0 ms, CLS 0,007.

## 5. Kiểm thử và phát hành

| Yêu cầu | Trạng thái | Bằng chứng chính |
|---|---|---|
| Unit/feature cho đơn, coupon, payment, GHN, email, đổi trả | Đạt local + Staging | `php artisan test`: 385 test, 1.470 assertion, 0 fail trên local; bản ngay trước thay đổi URL linh hoạt đã đạt 381 test/1.466 assertion trên VPS Staging. Sáu test JavaScript kiểm tra race/failure/recovery của địa chỉ checkout và hồ sơ đều đạt. |
| Webhook success/fail/replay/sai chữ ký | Đạt local | Feature tests và E2E VNPAY/MoMo/GHN. |
| Tranh chấp tồn kho thật | Đạt local + Staging | MySQL multi-process drill ngày 12-09-2026 trên Staging với 20 worker/5 tồn: đúng 5 thành công, 15 bị từ chối, tồn dừng ở 0. |
| E2E hành trình khách hàng | Đạt local + Staging | 74 pass, 2 skip WebKit/iPhone có chủ đích, 0 fail trên Chrome, WebKit/Safari, iPhone và Android; chạy trực tiếp trên VPS Staging ngày 12-09-2026. |
| Staging tách biệt | Đạt | `staging.tuphim.online` dùng HTTPS + Basic Auth, database/upload/admin/queue/scheduler riêng; full gate, backup và restore drill MySQL đã đạt. `scripts/staging-gate.sh` khóa bằng tên database và hỗ trợ netrc mode 400/600 cho smoke test qua Basic Auth. |
| Deploy checklist và rollback | Đạt VPS | Bản URL linh hoạt đã được triển khai Production ngày 12-09-2026. Snapshot nguồn loại `.env`, config cache, SQLite/test-results và metadata macOS; checksum, giải nén, manifest và Composer metadata đều được xác minh trên macOS/Ubuntu. Database được backup trong release thành `database-20260912-220741.sql`; snapshot rollback ngay trước release là `release-20260912-220513.tar.gz`. |
| Asset production được nén/cache | Đạt Production | CSS fingerprint được phục vụ qua HTTP/2 với gzip và `Cache-Control: immutable`; Lighthouse Production đạt 98 mobile/100 desktop. |

## Trạng thái Production và điều kiện bật tích hợp Live

### Rà soát bổ sung ngày 07-09-2026

- Đã sửa và triển khai riêng Staging: ba endpoint IPN/webhook chính xác được chuyển thẳng vào Laravel, không bị Basic Auth chặn. Probe không ký trên HTTPS: MoMo trả 400, VNPAY trả JSON RspCode 97, GHN trả JSON Unauthorized; trang chủ, đăng nhập, admin, index.php và các URL hậu tố vẫn trả 401 Basic Auth. Đây là bằng chứng đường truyền/kiểm tra từ chối, chưa phải giao dịch merchant thành công.
- Đã sửa và triển khai 2FA ở local, Staging và Production: phiên xác thực gắn với tài khoản và lần thiết lập, confirm không thay mã khôi phục của tài khoản đã bật, recovery code được tiêu thụ trong transaction có khóa.
- Preflight Staging xác nhận admin đã bật 2FA và SMTP thật sẵn sàng. Email cảnh báo đồng bộ và tracker exception nội bộ thay thế an toàn khi chưa có webhook/Sentry DSN; cả hai probe thật đã đạt.
- Trang thanh toán và địa chỉ lưu trong hồ sơ đã sửa, triển khai Staging và Production: phản hồi quận/phường/phí cũ bị bỏ qua, đổi khu vực xóa ID/tên/phí cũ ngay lập tức, lỗi tải có trạng thái rõ ràng và có thể phục hồi bằng chọn lại. Sáu test JavaScript với phản hồi bất đồng bộ điều khiển được đã qua. Ngày 12-09-2026, hành trình Chrome thật trên Staging đã đi qua đăng nhập, thêm giỏ, checkout, tải tỉnh/quận/phường và tính phí GHN; Lào Cai/Si Ma Cai trả phí 71.500đ và tổng 611.500đ. Tài khoản và Basic Auth kiểm thử tạm đã được xóa sau khi chụp bằng chứng.
- Health-check đã sửa và triển khai Staging: MoMo/VNPAY tắt có chủ đích không còn làm server báo lỗi; nếu bật thì vẫn bắt buộc cấu hình hợp lệ và endpoint truy cập được. Probe runtime Staging ngày 12-09-2026 xác nhận đủ 8/8 hạng mục đều xanh sau khi cấu hình SMTP và làm mới heartbeat scheduler.
- Health-check và 2FA mới đã triển khai Production ngày 12-09-2026. Sau khi phát hiện PHP-FPM không đọc được `.env` lúc cache bị xóa, quyền Production/Staging đã được chuẩn hóa thành `.env` `640` (`ubuntu:www-data`), log có group-write và cache cấu hình chứa bí mật là `640`; deploy script có cơ chế khôi phục cache cũ khi phát hành lỗi. Home và đăng nhập Production trở lại HTTP 200, worker đều RUNNING.
- Return URL thanh toán, link xác thực email và link đặt lại mật khẩu trên local đã linh hoạt theo origin đang dùng: thao tác từ `localhost` quay lại `localhost`, thao tác qua tunnel mới quay lại đúng tunnel đó ngay cả khi worker gửi mail sau request; Staging/Production luôn dùng URL cố định từ `.env`. Host không được tin cậy không thể trở thành return URL hay URL trong email.
- Sổ giao dịch VNPAY đã được nối đầy đủ vào `payment_transactions`: checkout ghi request đã loại IP, IPN mới được chốt paid/failed, payload lưu trữ loại chữ ký, callback lặp là no-op và thành công đến muộn trên đơn đã hủy chuyển sang quy trình đối soát hoàn tiền.
- VPS Staging đã có Node.js, Composer dev dependencies, SQLite PHP, Google Chrome và WebKit. Toàn bộ 381 PHP test/1.464 assertion, 6 JavaScript test và 76 lượt E2E (74 đạt, 2 skip có chủ đích) đã chạy đạt. E2E dùng cookie HTTP, database và cờ tắt rate limit riêng chỉ trong `APP_ENV=testing`; giới hạn bảo mật Staging/Production không thay đổi. Luồng COD WebKit còn được chạy lặp 3/3 lần trước full gate.
- Full `scripts/staging-gate.sh` ngày 12-09-2026 đã đạt build, Composer/npm audit, PHP/JS/E2E, MySQL concurrency, backup/restore, strict preflight, alert/incident probe, HTTPS/HSTS/Secure cookie, gzip và immutable cache. Tài khoản Basic Auth/netrc tạm của gate đã được khôi phục/xóa; Nginx hợp lệ và Production vẫn HTTP 200.
- GHN/MoMo/VNPAY hiện có cờ bật riêng. Tích hợp tắt có chủ đích không làm health/deploy đỏ và không xuất hiện như lựa chọn thanh toán; GHN tắt dùng phí giao hàng mặc định. Bật bất kỳ cổng nào vẫn bắt buộc đủ cấu hình và probe thành công.
- Credential GHN Production cũ trả upstream `401`, vì vậy `GHN_ENABLED=false` được đặt trước release thay vì tiếp tục gọi credential lỗi. Khi có credential Live hợp lệ, bật lại cờ và chạy strict gate trước khi dùng.
- Production đã triển khai bản release mới: strict preflight đạt mọi dòng, `/`, `/dang-nhap`, `/san-pham` và `/health` đều HTTP 200; health database/cache/queue/scheduler/mail xanh, ba tích hợp ngoài tắt có chủ đích được ghi `skipped`. Hai queue worker và scheduler đều `RUNNING`; không có incident chưa xử lý.
- Audit snapshot phát hiện các archive cũ có config cache. Năm cặp archive server đã bị hủy an toàn sau khi tạo bản sạch thay thế; năm cặp local chỉ chứa dữ liệu E2E đã chuyển vào Trash. Verifier hiện từ chối `.env`, config cache, SQLite E2E, test-results, `.DS_Store` và AppleDouble `._*`; sau lần phát hành cuối, cả mã nguồn lẫn runtime Production/Staging đều có 0 file metadata macOS.

Các mục sau không chặn hệ thống COD hiện tại, nhưng bắt buộc trước khi bật cổng tương ứng:

1. Test VNPAY/MoMo bằng tài khoản merchant và GHN callback thực tế trên Staging; automated E2E đã chứng minh chữ ký, IPN, replay và trạng thái nội bộ nhưng không thay cho giao dịch tại nhà cung cấp.
2. Cấp credential Live, đăng ký callback/IPN tại nhà cung cấp, bật đúng feature flag và chạy lại strict gate trước khi hiển thị phương thức đó cho khách.
3. Sentry và webhook cảnh báo bên ngoài là nâng cấp tùy chọn; email cảnh báo thật và tracker incident nội bộ đang đáp ứng giám sát tại chỗ.
Không mục nào ở trên được đánh dấu hoàn thành chỉ bằng fixture hoặc mock local.
