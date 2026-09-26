<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AdminTwoFactorSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function registerForm()
    {
        return view('auth.register');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['username' => 'required|string', 'password' => 'required']);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['username' => 'Tên đăng nhập hoặc mật khẩu không đúng.'])->onlyInput('username');
        }
        AdminTwoFactorSession::forget($request);
        $request->session()->regenerate();

        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('home'))->with('success', 'Đăng nhập thành công.');
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'username' => 'required|string|max:50|alpha_dash|unique:users', 'email' => 'required|email|unique:users', 'phone' => 'required|string|max:20', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $user = User::create(['name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'phone' => $data['phone'], 'password' => Hash::make($data['password'])]);
        Auth::login($user);
        event(new Registered($user));

        return redirect()->route('verification.notice')
            ->with('success', 'Tạo tài khoản thành công. Hãy xác thực email để tiếp tục.')
            ->with('verification_sent_at', now()->timestamp);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Bạn đã đăng xuất.');
    }
}
