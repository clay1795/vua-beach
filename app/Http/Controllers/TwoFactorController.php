<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AdminTwoFactorSession;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function setup(Request $request, Google2FA $google2fa)
    {
        abort_unless($request->user()->is_admin, 403);
        $user = DB::transaction(function () use ($request, $google2fa): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            if (! $user->two_factor_secret) {
                $user->forceFill([
                    'two_factor_secret' => $google2fa->generateSecretKey(),
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ])->save();
            }

            return $user;
        });
        if ($user->two_factor_confirmed_at) {
            return view('auth.two-factor-enabled');
        }
        AdminTwoFactorSession::forget($request);

        $uri = $google2fa->getQRCodeUrl((string) config('app.name'), $user->email, $user->two_factor_secret);
        $qrCode = (new Writer(new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd)))->writeString($uri);

        return response()->view('auth.two-factor-setup', compact('user', 'qrCode'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function confirm(Request $request, Google2FA $google2fa)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        [$user, $plainCodes] = DB::transaction(function () use ($request, $google2fa, $data): array {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            if ($user->two_factor_confirmed_at) {
                throw ValidationException::withMessages(['code' => 'Xác thực hai lớp đã được bật. Vui lòng dùng màn hình kiểm tra mã.']);
            }
            if (! $user->two_factor_secret || ! $google2fa->verifyKey($user->two_factor_secret, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'Mã xác thực không đúng hoặc đã hết hạn.']);
            }

            $plainCodes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();
            $user->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $plainCodes),
            ])->save();

            return [$user, $plainCodes];
        });
        AdminTwoFactorSession::markVerified($request, $user);

        return redirect()->route('admin.dashboard')->with('success', 'Đã bật xác thực hai lớp.')->with('recovery_codes', $plainCodes);
    }

    public function challenge(Request $request)
    {
        abort_unless($request->user()->is_admin, 403);
        if (! $request->user()->two_factor_confirmed_at || ! $request->user()->two_factor_secret) {
            AdminTwoFactorSession::forget($request);

            return redirect()->route('two-factor.setup');
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request, Google2FA $google2fa)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $code = strtoupper(trim($data['code']));
        $user = DB::transaction(function () use ($request, $google2fa, $code): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
                throw ValidationException::withMessages(['code' => 'Bạn cần thiết lập xác thực hai lớp trước khi kiểm tra mã.']);
            }
            $validTotp = ctype_digit($code) && strlen($code) === 6
                && $google2fa->verifyKey($user->two_factor_secret, $code);
            $recoveryCodes = $user->two_factor_recovery_codes ?? [];
            $recoveryIndex = collect($recoveryCodes)->search(fn (string $hash) => Hash::check($code, $hash));

            if (! $validTotp && $recoveryIndex === false) {
                throw ValidationException::withMessages(['code' => 'Mã xác thực hoặc mã khôi phục không đúng.']);
            }
            if ($recoveryIndex !== false) {
                unset($recoveryCodes[$recoveryIndex]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();
            }

            return $user;
        });

        AdminTwoFactorSession::markVerified($request, $user);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function disable(Request $request, Google2FA $google2fa)
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'digits:6'],
        ]);
        DB::transaction(function () use ($request, $google2fa, $data): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
            if (! Hash::check($data['password'], $user->getAuthPassword())) {
                throw ValidationException::withMessages(['password' => 'Mật khẩu hiện tại không đúng.']);
            }
            if (! $user->two_factor_confirmed_at || ! $user->two_factor_secret || ! $google2fa->verifyKey($user->two_factor_secret, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'Mã xác thực không đúng hoặc đã hết hạn.']);
            }

            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        });
        AdminTwoFactorSession::forget($request);

        return redirect()->route('profile.edit')->with('success', 'Đã tắt xác thực hai lớp.');
    }
}
