<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class AdminTwoFactorSession
{
    public static function markVerified(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put([
            'admin_2fa_verified_at' => now()->timestamp,
            'admin_2fa_user_id' => (string) $user->getKey(),
            'admin_2fa_enrollment' => self::enrollmentFingerprint($user),
        ]);
    }

    public static function isVerified(Request $request, User $user): bool
    {
        $verifiedAt = (int) $request->session()->get('admin_2fa_verified_at', 0);

        return $user->two_factor_secret && $user->two_factor_confirmed_at
            && (string) $request->session()->get('admin_2fa_user_id') === (string) $user->getKey()
            && $verifiedAt >= now()->subHours(8)->timestamp
            && $verifiedAt <= now()->timestamp
            && hash_equals(self::enrollmentFingerprint($user), (string) $request->session()->get('admin_2fa_enrollment', ''));
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget([
            'admin_2fa_verified_at', 'admin_2fa_user_id', 'admin_2fa_enrollment', 'recovery_codes',
        ]);
    }

    private static function enrollmentFingerprint(User $user): string
    {
        // Re-enrollment and password changes invalidate earlier sessions, even within the same second.
        return hash_hmac('sha256', implode('|', [
            $user->getKey(),
            $user->two_factor_secret,
            $user->two_factor_confirmed_at?->getTimestamp(),
            $user->getAuthPassword(),
        ]), (string) config('app.key'));
    }
}
