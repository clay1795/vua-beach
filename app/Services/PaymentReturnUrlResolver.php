<?php

namespace App\Services;

use Illuminate\Http\Request;

class PaymentReturnUrlResolver
{
    public function applicationOrigin(Request $request, string $trustedCompanionUrl = ''): string
    {
        $configuredOrigin = rtrim((string) config('app.url'), '/');

        if (! app()->environment('local')) {
            return $configuredOrigin;
        }

        $scheme = strtolower($request->getScheme());
        $host = strtolower($request->getHost());
        $localHosts = ['localhost', '127.0.0.1', '::1'];
        $isLocalOrigin = in_array($host, $localHosts, true) && in_array($scheme, ['http', 'https'], true);
        $isTrustedCompanion = $scheme === 'https'
            && $host !== ''
            && hash_equals(strtolower((string) parse_url($trustedCompanionUrl, PHP_URL_HOST)), $host);
        $isAllowedLocalTunnel = $scheme === 'https'
            && collect(config('services.local_tunnel_host_suffixes', []))
                ->contains(fn (string $suffix): bool => $suffix !== ''
                    && str_ends_with($host, $suffix)
                    && strlen($host) > strlen($suffix));

        if (! $isLocalOrigin && ! $isTrustedCompanion && ! $isAllowedLocalTunnel) {
            return $configuredOrigin;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    public function resolve(Request $request, string $routeName, string $configuredUrl, string $ipnUrl): string
    {
        if (! app()->environment('local')) {
            return $configuredUrl;
        }

        $origin = $this->applicationOrigin($request, $ipnUrl);
        if ($origin === rtrim((string) config('app.url'), '/')
            && ! hash_equals($origin, rtrim($request->getSchemeAndHttpHost(), '/'))) {
            return $configuredUrl;
        }

        return $origin.route($routeName, absolute: false);
    }
}
