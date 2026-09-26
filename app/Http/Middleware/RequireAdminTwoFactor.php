<?php

namespace App\Http\Middleware;

use App\Support\AdminTwoFactorSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        if (! $request->user()?->two_factor_confirmed_at || ! $request->user()?->two_factor_secret) {
            AdminTwoFactorSession::forget($request);

            return redirect()->route('two-factor.setup')->with('warning', 'Quản trị viên phải bật xác thực hai lớp trước khi tiếp tục.');
        }

        if (! AdminTwoFactorSession::isVerified($request, $request->user())) {
            AdminTwoFactorSession::forget($request);

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
