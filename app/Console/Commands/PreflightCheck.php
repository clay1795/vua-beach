<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminCredentialReadiness;
use App\Services\CatalogReadiness;
use App\Services\GHNService;
use App\Services\MomoService;
use App\Services\PublicEnvironmentReadiness;
use App\Services\ShippingStateMachine;
use App\Services\VnpayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class PreflightCheck extends Command
{
    protected $signature = 'vua-beach:preflight
        {--strict : Return a failure code when a warning exists}
        {--allow-pending-migrations : Allow schema checks to wait for the migration step of a guarded release}';

    protected $description = 'Kiểm tra an toàn môi trường, dữ liệu và tích hợp trước khi triển khai';

    public function handle(GHNService $ghn, VnpayService $vnpay, MomoService $momo, PublicEnvironmentReadiness $readiness, CatalogReadiness $catalogReadiness, AdminCredentialReadiness $adminCredentials): int
    {
        $warnings = [];
        $checks = [];
        $check = function (string $label, bool $passed, ?string $warning = null) use (&$checks, &$warnings): void {
            $checks[] = [$passed ? 'Đạt' : 'Cần xử lý', $label];
            if (! $passed && $warning) {
                $warnings[] = $warning;
            }
        };

        try {
            $activeProducts = Product::query()->where('status', 'active')->withExists('variants')->get();
            $orderCount = Order::count();
            $shippingMismatchCount = Order::query()
                ->where('status', 'completed')
                ->whereNotNull('ghn_order_code')
                ->where('shipping_status', '!=', 'delivered')
                ->count();
            $unknownShippingCount = Order::query()
                ->whereNotIn('shipping_status', array_keys(ShippingStateMachine::TRANSITIONS))
                ->count();
            $invalidPaymentCombinationCount = Order::query()
                ->where(function ($query) {
                    $query->where(function ($cancelled) {
                        $cancelled->where('status', 'cancelled')
                            ->where('payment_method', '!=', 'cod')
                            ->whereIn('payment_status', ['pending', 'paid']);
                    })->orWhere(function ($refundPending) {
                        $refundPending->where('status', '!=', 'cancelled')
                            ->where('payment_status', 'refund_pending');
                    });
                })
                ->count();
            $check('Kết nối database', true);
            $check('Có sản phẩm đang bán', Product::where('status', 'active')->exists(), 'Catalog đang trống: hãy thêm sản phẩm qua Admin trước khi mở bán.');
            $placeholderProducts = $activeProducts->filter(fn (Product $product) => $catalogReadiness->looksLikePlaceholder($product));
            $productsWithoutLocalImage = $activeProducts->reject(fn (Product $product) => $catalogReadiness->hasReadableLocalImage($product));
            $productsWithoutVariants = $activeProducts->reject(fn (Product $product) => (bool) $product->variants_exists);
            $check(
                'Không còn sản phẩm test/demo đang bán',
                $placeholderProducts->isEmpty(),
                $placeholderProducts->isNotEmpty() ? 'Ẩn sản phẩm dữ liệu thử: '.$placeholderProducts->pluck('name')->join(', ').'.' : null,
            );
            $check(
                'Sản phẩm đang bán có ảnh local hợp lệ',
                $productsWithoutLocalImage->isEmpty(),
                $productsWithoutLocalImage->isNotEmpty() ? 'Bổ sung ảnh local cho: '.$productsWithoutLocalImage->pluck('name')->join(', ').'.' : null,
            );
            $check(
                'Mỗi sản phẩm đang bán có biến thể',
                $productsWithoutVariants->isEmpty(),
                $productsWithoutVariants->isNotEmpty() ? 'Sản phẩm đang bán thiếu biến thể size/màu: '.$productsWithoutVariants->pluck('name')->join(', ').'.' : null,
            );
            $check('Dữ liệu đơn hàng', $orderCount >= 0);
            $check(
                'Trạng thái đơn hoàn thành và vận chuyển nhất quán',
                $shippingMismatchCount === 0,
                $shippingMismatchCount > 0 ? "Có {$shippingMismatchCount} đơn đã hoàn thành nhưng vận chuyển chưa ghi nhận đã giao." : null,
            );
            $check(
                'Trạng thái vận chuyển dùng tập canonical',
                $unknownShippingCount === 0,
                $unknownShippingCount > 0 ? "Có {$unknownShippingCount} đơn dùng trạng thái vận chuyển không được hỗ trợ." : null,
            );
            $check(
                'Trạng thái hủy và hoàn tiền nhất quán',
                $invalidPaymentCombinationCount === 0,
                $invalidPaymentCombinationCount > 0 ? "Có {$invalidPaymentCombinationCount} đơn có tổ hợp trạng thái hủy/thanh toán cần đối soát." : null,
            );

            $ledgerExists = Schema::hasTable('payment_transactions');
            $check(
                'Bảng sổ giao dịch thanh toán tồn tại',
                $ledgerExists || $this->option('allow-pending-migrations'),
                'Thiếu bảng payment_transactions; chạy migration trước khi phục vụ checkout.',
            );
            if ($ledgerExists) {
                $ordersWithoutTransactions = Order::query()->doesntHave('paymentTransactions')->count();
                $invalidTransactions = PaymentTransaction::query()
                    ->whereNotIn('gateway', ['cod', 'momo', 'vnpay'])
                    ->orWhereNotIn('status', array_keys(PaymentTransaction::STATUS_LABELS))
                    ->orWhere('amount', '<=', 0)
                    ->count();
                $settledOrdersWithoutLedgerProof = Order::query()
                    ->where('payment_method', '!=', 'cod')
                    ->whereIn('payment_status', ['paid', 'refund_pending', 'refunded'])
                    ->whereDoesntHave('paymentTransactions', fn ($query) => $query->whereIn('status', ['paid', 'refund_pending', 'refunded']))
                    ->count();
                $check(
                    'Mỗi đơn hàng có lịch sử giao dịch',
                    $ordersWithoutTransactions === 0,
                    $ordersWithoutTransactions > 0 ? "Có {$ordersWithoutTransactions} đơn chưa có dòng payment_transactions." : null,
                );
                $check(
                    'Dữ liệu giao dịch dùng trạng thái và cổng hợp lệ',
                    $invalidTransactions === 0,
                    $invalidTransactions > 0 ? "Có {$invalidTransactions} giao dịch có cổng, trạng thái hoặc số tiền không hợp lệ." : null,
                );
                $check(
                    'Đơn online đã thu tiền có bằng chứng giao dịch',
                    $settledOrdersWithoutLedgerProof === 0,
                    $settledOrdersWithoutLedgerProof > 0 ? "Có {$settledOrdersWithoutLedgerProof} đơn online đã ghi nhận tiền nhưng thiếu giao dịch thành công/hoàn tiền." : null,
                );
            }
        } catch (\Throwable $exception) {
            $check('Kết nối database', false, 'Không thể đọc database: '.$exception->getMessage());
        }

        $check('Storage public đã liên kết', is_link(public_path('storage')), 'Chạy: php artisan storage:link');
        $check('PHP tối ưu được ảnh WebP', extension_loaded('gd') && function_exists('imagewebp'), 'Bật PHP GD có hỗ trợ WebP để ảnh sản phẩm tải lên được nén tự động.');
        $check('Bảng queue tồn tại', Schema::hasTable('jobs') && Schema::hasTable('failed_jobs'), 'Thiếu bảng queue; chạy migration trước khi dùng email hàng đợi.');
        $queueRunsInline = in_array(config('queue.default'), ['sync', 'deferred', 'background'], true);
        $check(
            'Queue xử lý job sẵn sàng',
            $queueRunsInline || $this->queueWorkerIsRunning(),
            'Chưa có queue worker; chạy: php artisan queue:work --tries=3 hoặc dùng QUEUE_CONNECTION=sync trên một Web Service nhỏ.',
        );
        $check('Scheduler đang chạy', $this->schedulerIsRunning(), 'Chưa có scheduler; chạy schedule:work bằng launchd hoặc Supervisor.');
        [$mailReady, $mailWarning] = $this->mailIsReady();
        $check('SMTP gửi mail đã sẵn sàng', $mailReady, $mailWarning);
        $ghnEnabled = filter_var(config('services.ghn.enabled'), FILTER_VALIDATE_BOOLEAN);
        $check(
            'GHN sẵn sàng hoặc đã tắt có chủ đích',
            ! $ghnEnabled || $ghn->configured(),
            'GHN đang bật nhưng chưa đủ cấu hình; đặt GHN_ENABLED=false để dùng phí tạm tính hoặc điền đủ credential.',
        );
        if (filter_var(config('services.payment_sandbox_mode'), FILTER_VALIDATE_BOOLEAN)) {
            $check('Chế độ thanh toán Sandbox đã được xác nhận', true);
        }
        $momoEnabled = filter_var(config('services.momo.enabled'), FILTER_VALIDATE_BOOLEAN);
        $momoIssues = $momoEnabled ? $momo->configurationIssues() : [];
        $check(
            'MoMo sẵn sàng hoặc đã tắt có chủ đích',
            $momoIssues === [],
            $momoIssues ? 'MoMo: '.implode(' ', $momoIssues) : null,
        );
        $vnpayEnabled = filter_var(config('services.vnpay.enabled'), FILTER_VALIDATE_BOOLEAN);
        $vnpayIssues = $vnpayEnabled ? $vnpay->configurationIssues() : [];
        $check(
            'VNPAY sẵn sàng hoặc đã tắt có chủ đích',
            $vnpayIssues === [],
            $vnpayIssues ? 'VNPAY: '.implode(' ', $vnpayIssues) : null,
        );

        if (app()->environment(['production', 'staging'])) {
            $environment = app()->environment('production') ? 'Production' : 'Staging';
            $check('APP_KEY mã hóa hợp lệ', $readiness->appKeyIsValid(), "{$environment} phải có APP_KEY hợp lệ do php artisan key:generate tạo.");
            $check("{$environment} tắt debug", ! config('app.debug'), "{$environment} phải đặt APP_DEBUG=false.");
            $check("{$environment} dùng HTTPS công khai", $readiness->publicHttpsUrl(config('app.url')), "{$environment} phải đặt APP_URL bằng HTTPS công khai, không dùng URL mẫu hoặc localhost.");
            $check('Cookie phiên chỉ gửi qua HTTPS', config('session.secure') === true, 'Máy chủ public phải đặt SESSION_SECURE_COOKIE=true.');
            $check(
                'Session public lưu trong database',
                config('session.driver') === 'database' && Schema::hasTable((string) config('session.table', 'sessions')),
                'Đặt SESSION_DRIVER=database và chạy migration để đổi mật khẩu có thể thu hồi ngay các phiên trên thiết bị khác.',
            );
            $queueConnection = (string) config('queue.default');
            $check(
                'Queue chỉ phát job sau khi transaction commit',
                config("queue.connections.{$queueConnection}.after_commit") === true,
                'Đặt QUEUE_AFTER_COMMIT=true để email/job không đọc dữ liệu chưa commit hoặc vẫn chạy sau rollback.',
            );
            $trustedProxies = config('http_security.trusted_proxies', []);
            $check('Reverse proxy không tin cậy toàn Internet', ! array_intersect($trustedProxies, ['*', '**']), 'TRUSTED_PROXIES không được dùng * hoặc **; hãy khai báo đúng IP/CIDR của reverse proxy.');
            $alertWebhookReady = $readiness->publicHttpsUrl(config('services.alerts.webhook_url'));
            $alertEmailReady = $mailReady && filter_var(config('services.alerts.mail_to'), FILTER_VALIDATE_EMAIL) !== false;
            $externalSentryReady = $readiness->publicHttpsUrl(config('sentry.dsn'));
            $internalTrackerSchemaReady = Schema::hasTable('exception_incidents') || $this->option('allow-pending-migrations');
            $internalTrackerReady = $internalTrackerSchemaReady && ($alertWebhookReady || $alertEmailReady);
            $check(
                'Theo dõi exception đã cấu hình',
                $externalSentryReady || $internalTrackerReady,
                'Đặt SENTRY_LARAVEL_DSN HTTPS hoặc chạy migration và cấu hình ALERT_MAIL_TO/ALERT_WEBHOOK_URL cho bộ theo dõi nội bộ.',
            );
            $check(
                'Sentry không thu request body nhạy cảm',
                config('sentry.send_default_pii') === false && config('sentry.max_request_body_size') === 'never',
                'Đặt SENTRY_SEND_DEFAULT_PII=false và SENTRY_MAX_REQUEST_BODY_SIZE=never để mật khẩu/form không rời máy chủ.',
            );
            $check(
                'Kênh cảnh báo mail đã cấu hình',
                $alertWebhookReady || $alertEmailReady,
                'Đặt ALERT_WEBHOOK_URL HTTPS công khai hoặc ALERT_MAIL_TO hợp lệ để nhận cảnh báo khi mail retry hết.',
            );
            $check('GHN webhook có khóa bảo vệ', $readiness->secretHasMinimumBytes(config('services.ghn.webhook_secret'), 32), 'Đặt GHN_WEBHOOK_SECRET ngẫu nhiên tối thiểu 32 byte trước khi đăng ký webhook GHN.');
            $webhookPayloadLimit = (int) config('webhooks.max_payload_bytes');
            $check(
                'Webhook có giới hạn payload an toàn',
                $webhookPayloadLimit >= 1024 && $webhookPayloadLimit <= 1048576,
                'Đặt WEBHOOK_MAX_PAYLOAD_BYTES trong khoảng 1024 đến 1048576 byte (khuyến nghị 65536).',
            );
            if (app()->environment('staging')) {
                $database = DB::connection()->getDatabaseName();
                $check(
                    'Staging dùng database được đánh dấu riêng',
                    $readiness->stagingDatabaseIsGuarded($database, config('deployment.staging_database_guard')),
                    'STAGING_DATABASE_GUARD phải khớp chính xác database đang kết nối và tên database phải chứa staging/stage/uat/preprod.',
                );
            }
        } else {
            $check('Local tách môi trường test', app()->environment('local'), 'Chỉ chạy kiểm thử trong APP_ENV=testing.');
        }

        try {
            $admins = User::query()->where('is_admin', true)->get();
            $usesLegacyPassword = $admins->contains(
                fn (User $admin) => $adminCredentials->usesKnownDefaultPassword($admin),
            );
            $check('Mật khẩu admin không còn là mặc định/phổ biến', $admins->isNotEmpty() && ! $usesLegacyPassword, $admins->isEmpty()
                ? 'Hệ thống chưa có tài khoản quản trị.'
                : 'Có tài khoản quản trị vẫn dùng mật khẩu mặc định hoặc quá phổ biến; chạy admin:rotate-default-password ngay.');
            if (app()->environment(['production', 'staging'])) {
                $unverifiedAdmins = $admins->whereNull('email_verified_at')->count();
                $adminsWithoutTwoFactor = $admins->whereNull('two_factor_confirmed_at')->count();
                $check('Mọi quản trị viên đã xác thực email', $unverifiedAdmins === 0, "Có {$unverifiedAdmins} quản trị viên chưa xác thực email.");
                $check('Mọi quản trị viên đã bật xác thực hai lớp', $adminsWithoutTwoFactor === 0, "Có {$adminsWithoutTwoFactor} quản trị viên chưa hoàn tất 2FA.");
            }
        } catch (\Throwable) {
            $check('Mật khẩu admin không còn là mặc định/phổ biến', false, 'Không thể kiểm tra tài khoản quản trị.');
        }

        $this->table(['Trạng thái', 'Hạng mục'], $checks);
        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        return $this->option('strict') && $warnings ? self::FAILURE : self::SUCCESS;
    }

    private function queueWorkerIsRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        $process = new Process(['pgrep', '-f', '[a]rtisan queue:(work|listen)']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    private function schedulerIsRunning(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        $process = new Process(['pgrep', '-f', '[a]rtisan schedule:(work|run)']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    /** @return array{bool, ?string} */
    private function mailIsReady(): array
    {
        if (config('mail.default') !== 'smtp') {
            return [false, 'MAIL_MAILER phải là smtp để gửi email thật.'];
        }

        try {
            $transport = Mail::mailer()->getSymfonyTransport();
            if (method_exists($transport, 'start')) {
                $transport->start();
            }
            if (method_exists($transport, 'stop')) {
                $transport->stop();
            }

            return [true, null];
        } catch (\Throwable) {
            return [false, 'Không thể kết nối hoặc xác thực SMTP; hãy kiểm tra host, cổng, tài khoản và App Password.'];
        }
    }
}
