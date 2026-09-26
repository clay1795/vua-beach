<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;

class PublicEnvironmentReadiness
{
    public function appKeyIsValid(): bool
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false) {
                return false;
            }
            $key = $decoded;
        }

        return Encrypter::supported($key, (string) config('app.cipher'));
    }

    public function publicHttpsUrl(mixed $url): bool
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1', 'example.com'], true)) {
            return false;
        }
        if (str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    public function sameOriginPublicHttpsUrl(mixed $url, mixed $applicationUrl): bool
    {
        if (! $this->publicHttpsUrl($url) || ! $this->publicHttpsUrl($applicationUrl)) {
            return false;
        }

        return $this->origin($url) === $this->origin($applicationUrl);
    }

    public function secretHasMinimumBytes(mixed $secret, int $minimum): bool
    {
        return is_string($secret) && strlen($secret) >= $minimum;
    }

    public function stagingDatabaseIsGuarded(mixed $database, mixed $guard): bool
    {
        if (! is_string($database) || ! is_string($guard) || $database === '' || $guard === '') {
            return false;
        }

        return hash_equals($database, $guard)
            && preg_match('/(?:staging|stage|uat|preprod)/i', $database) === 1;
    }

    private function origin(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $port = parse_url($url, PHP_URL_PORT);

        return $scheme.'://'.$host.($port === null ? '' : ':'.$port);
    }
}
