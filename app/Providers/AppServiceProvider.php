<?php

namespace App\Providers;

use App\Listeners\AlertOnFailedMailJob;
use App\Models\Category;
use App\Models\SiteSetting;
use App\Services\RuntimeHeartbeat;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(JobFailed::class, AlertOnFailedMailJob::class);
        Queue::looping(fn () => app(RuntimeHeartbeat::class)->beat(RuntimeHeartbeat::QUEUE));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('login', function (Request $request): array {
            if (app()->environment('testing') && config('app.e2e_disable_rate_limits')) {
                return [Limit::none()];
            }

            $account = hash('sha256', mb_strtolower(trim((string) $request->input('username'))));

            return [
                Limit::perMinute(5)->by('login-account:'.$account.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('registration', function (Request $request): array {
            $email = hash('sha256', mb_strtolower(trim((string) $request->input('email'))));

            return [
                Limit::perMinute(5)->by('registration-email:'.$email.'|'.$request->ip()),
                Limit::perHour(20)->by('registration-ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('verification-resend', function (Request $request): array {
            $identity = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [
                Limit::perMinute(1)->by('verification-minute:'.$identity),
                Limit::perHour(6)->by('verification-hour:'.$identity),
            ];
        });
        RateLimiter::for('password-reset-link', function (Request $request): array {
            $email = hash('sha256', mb_strtolower(trim((string) $request->input('email'))));

            return [
                Limit::perMinute(3)->by('password-reset-email:'.$email.'|'.$request->ip()),
                Limit::perHour(20)->by('password-reset-ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by('password-reset-submit:'.$request->ip()));

        // Khi chạy sau reverse proxy (ngrok/hosting HTTPS), request nội bộ có
        // thể là HTTP. Không ép HTTPS cho localhost, nếu không CSS/JS sẽ bị
        // trình duyệt chặn khi mở trực tiếp bằng http://localhost:8000.
        $host = request()->getHost();
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        // A local app may be shared through a public tunnel for payment callbacks.
        // Never expose debug exception details outside localhost.
        if (! $isLocalHost) {
            config(['app.debug' => false]);
        }

        if (! $isLocalHost && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Paginator::useBootstrapFive();

        View::composer('*', function ($view) {
            $view->with('siteSettings', SiteSetting::current());
        });

        View::composer('layouts.app', function ($view) {
            $view->with('navCategories', Category::query()->orderBy('name')->limit(6)->get());
        });
    }
}
