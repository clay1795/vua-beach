<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends AdminController
{
    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.users.create', ['user' => new User]);
    }

    public function show(User $user)
    {
        $this->authorizeAdmin();

        return view('admin.users.show', ['user' => $user->loadCount('orders')]);
    }

    public function edit(User $user)
    {
        $this->authorizeAdmin();

        return view('admin.users.edit', compact('user'));
    }

    private function validatedUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'role' => 'required|in:customer,admin',
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $this->validatedUser($request);
        $data['is_admin'] = $data['role'] === 'admin';
        unset($data['role']);
        $user = User::create($data);
        $this->audit('user.created', $user, 'Tạo tài khoản quản trị.');

        return redirect()->route('admin.users.show', $user)->with('success', 'Đã tạo tài khoản. Người dùng cần xác thực email khi đăng nhập.');
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin();
        $data = $this->validatedUser($request, $user);
        DB::transaction(function () use ($user, $data) {
            $admins = User::where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $makeAdmin = $data['role'] === 'admin';
            if (($locked->is(auth()->user()) && $locked->is_admin !== $makeAdmin) || ($locked->is_admin && ! $makeAdmin && $admins->count() <= 1)) {
                throw ValidationException::withMessages(['role' => 'Không thể hạ quyền tài khoản đang đăng nhập hoặc quản trị viên cuối cùng.']);
            }
            $attributes = collect($data)->except('role')->all();
            if (blank($attributes['password'] ?? null)) {
                unset($attributes['password']);
            }
            if ($locked->email !== $data['email']) {
                $attributes['email_verified_at'] = null;
            }
            $locked->forceFill($attributes + ['is_admin' => $makeAdmin])->save();
            $this->audit('user.updated', $locked, 'Cập nhật thông tin tài khoản.');
        });

        return redirect()->route('admin.users.show', $user)->with('success', 'Đã cập nhật tài khoản.');
    }

    public function destroy(User $user)
    {
        $this->authorizeAdmin();
        DB::transaction(function () use ($user) {
            $admins = User::where('is_admin', true)->orderBy('id')->lockForUpdate()->get();
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->is(auth()->user()) || ($locked->is_admin && $admins->count() <= 1) || $locked->orders()->exists()) {
                throw ValidationException::withMessages(['user' => 'Không thể xóa tài khoản đang đăng nhập, quản trị viên cuối cùng hoặc tài khoản có đơn hàng.']);
            }
            $this->audit('user.deleted', $locked, 'Xóa tài khoản chưa có đơn hàng.');
            $locked->delete();
        });

        return redirect()->route('admin.users.index')->with('success', 'Đã xóa tài khoản.');
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $search = trim((string) $request->query('q'));
        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function updateRole(Request $request, User $user)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'role' => ['required', 'in:customer,admin'],
        ]);
        $makeAdmin = $data['role'] === 'admin';

        if ($user->is(auth()->user())) {
            return back()->with('error', 'Bạn không thể tự thay đổi quyền của tài khoản đang đăng nhập.');
        }

        if ($user->is_admin && ! $makeAdmin && User::where('is_admin', true)->count() <= 1) {
            return back()->with('error', 'Cửa hàng phải luôn có ít nhất một quản trị viên.');
        }

        $user->update(['is_admin' => $makeAdmin]);
        $this->audit('user.role_updated', $user, 'Đổi quyền tài khoản '.$user->email.' thành '.($makeAdmin ? 'quản trị viên' : 'khách hàng').'.', ['is_admin' => $makeAdmin]);

        return back()->with('success', 'Đã cập nhật quyền cho tài khoản '.$user->name.'.');
    }
}
