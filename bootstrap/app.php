<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\RejectOversizedWebhookPayload;
use App\Http\Middleware\RequireAdminTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustConfiguredProxies;
use App\Services\ExceptionIncidentRecorder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The replacement reads from Laravel's configuration during requests,
        // after cached configuration has been loaded.
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        $middleware->trustHosts(
            at: static function (): array {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST);

                return is_string($host) && $host !== ''
                    ? ['^'.preg_quote($host).'$']
                    : [];
            },
            subdomains: false,
        );

        // Append so Laravel's TrustProxies middleware has normalized the request
        // before security headers decide whether the connection is HTTPS.
        $middleware->append(SecurityHeaders::class);
        // Bind every authenticated browser session to the current password hash.
        // After a password change, stale sessions on other devices are rejected.
        $middleware->appendToGroup('web', AuthenticateSession::class);
        // VNPAY calls this URL directly from its server, so it has no browser CSRF token.
        $middleware->validateCsrfTokens(except: ['thanh-toan/vnpay/ipn', 'thanh-toan/momo/ipn', 'van-chuyen/ghn/webhook']);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'admin.2fa' => RequireAdminTwoFactor::class,
            'webhook.payload' => RejectOversizedWebhookPayload::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
        $exceptions->report(function (Throwable $exception): void {
            app(ExceptionIncidentRecorder::class)->record(
                $exception,
                app()->runningInConsole() ? null : request(),
            );
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($status < 400 || $status >= 600) {
                return $response;
            }

            $messages = [
                400 => ['Yêu cầu không hợp lệ', 'Thông tin gửi lên không hợp lệ. Vui lòng kiểm tra và thử lại.'],
                401 => ['Cần đăng nhập', 'Vui lòng đăng nhập để tiếp tục.'],
                403 => ['Không có quyền truy cập', 'Bạn không có quyền thực hiện thao tác này.'],
                404 => ['Không tìm thấy trang', 'Trang hoặc dữ liệu bạn yêu cầu không còn tồn tại.'],
                405 => ['Thao tác không được hỗ trợ', 'Yêu cầu này không được phép thực hiện.'],
                419 => ['Phiên làm việc đã hết hạn', 'Vui lòng tải lại trang và thử lại.'],
                422 => ['Dữ liệu chưa hợp lệ', 'Vui lòng kiểm tra lại các thông tin đã nhập.'],
                429 => ['Thao tác quá nhanh', 'Bạn đã thực hiện quá nhiều lần. Vui lòng chờ một lát rồi thử lại.'],
                500 => ['Hệ thống đang gặp sự cố', 'Vui lòng thử lại sau ít phút. Nếu lỗi vẫn tiếp diễn, hãy liên hệ cửa hàng để được hỗ trợ.'],
                503 => ['Dịch vụ tạm thời gián đoạn', 'Hệ thống đang bảo trì hoặc chưa sẵn sàng. Vui lòng thử lại sau.'],
            ];
            [$title, $message] = $messages[$status] ?? ['Không thể xử lý yêu cầu', 'Đã xảy ra lỗi khi xử lý yêu cầu của bạn. Vui lòng thử lại sau.'];

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message], $status);
            }

            return response()->view('errors.fallback', compact('status', 'title', 'message'), $status);
        });
    })->create();
