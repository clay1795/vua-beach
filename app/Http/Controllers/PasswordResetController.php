<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Always return the same response so this endpoint cannot be used to discover accounts.
        Password::sendResetLink(['email' => $data['email']]);

        return back()->with('status', 'Nếu email thuộc một tài khoản, liên kết đặt lại mật khẩu sẽ được gửi trong ít phút.');
    }

    public function resetForm(Request $request, string $token)
    {
        return response()->view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                if (config('session.driver') === 'database') {
                    DB::table((string) config('session.table', 'sessions'))
                        ->where('user_id', $user->id)
                        ->delete();
                }
            });

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Mật khẩu đã được đặt lại. Bạn có thể đăng nhập ngay.');
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
