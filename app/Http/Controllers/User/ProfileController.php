<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ShippingAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user(), 'addresses' => $request->user()->shippingAddresses()->orderByDesc('is_default')->latest()->get()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $requiresCurrentPassword = $request->filled('password') || (string) $request->input('email') !== (string) $user->email;
        $request->session()->flash('validation_context', 'profile');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'current_password' => [Rule::requiredIf($requiresCurrentPassword), 'nullable', 'current_password:web'],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $emailChanged = $user->email !== $data['email'];
        unset($data['current_password']);
        $passwordChanged = filled($data['password']);
        if (blank($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        $user->fill($data);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $currentSessionId = $request->session()->getId();
        DB::transaction(function () use ($user, $passwordChanged, $currentSessionId): void {
            $user->save();
            if ($passwordChanged && config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            }
        });
        if ($passwordChanged) {
            $request->session()->regenerate();
        }
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')
                ->with('success', 'Email đã thay đổi. Hãy xác thực địa chỉ email mới.')
                ->with('verification_sent_at', now()->timestamp);
        }

        return back()->with('success', 'Đã cập nhật thông tin tài khoản.');
    }

    public function storeAddress(Request $request)
    {
        $request->session()->flash('validation_context', 'address-create');
        $data = $this->addressData($request);
        $user = $request->user();
        DB::transaction(function () use ($data, $user) {
            $default = ! empty($data['is_default']) || ! $user->shippingAddresses()->exists();
            if ($default) {
                $user->shippingAddresses()->update(['is_default' => false]);
            } $user->shippingAddresses()->create($data + ['is_default' => $default]);
        });

        return back()->with('success', 'Đã thêm địa chỉ giao hàng.');
    }

    public function updateAddress(Request $request, ShippingAddress $shippingAddress)
    {
        abort_unless($shippingAddress->user_id === $request->user()->id, 403);
        $request->session()->flash('validation_context', 'address-'.$shippingAddress->id);
        $data = $this->addressData($request);
        DB::transaction(function () use ($data, $shippingAddress, $request) {
            if (! empty($data['is_default'])) {
                $request->user()->shippingAddresses()->update(['is_default' => false]);
            }
            $shippingAddress->update($data + ['is_default' => ! empty($data['is_default']) || $shippingAddress->is_default]);
        });

        return back()->with('success', 'Đã cập nhật địa chỉ giao hàng.');
    }

    public function setDefaultAddress(Request $request, ShippingAddress $shippingAddress)
    {
        abort_unless($shippingAddress->user_id === $request->user()->id, 403);
        DB::transaction(function () use ($request, $shippingAddress) {
            $request->user()->shippingAddresses()->update(['is_default' => false]);
            $shippingAddress->update(['is_default' => true]);
        });

        return back()->with('success', 'Đã đặt làm địa chỉ mặc định.');
    }

    public function destroyAddress(Request $request, ShippingAddress $shippingAddress)
    {
        abort_unless($shippingAddress->user_id === $request->user()->id, 403);
        DB::transaction(function () use ($request, $shippingAddress) {
            $default = $shippingAddress->is_default;
            $shippingAddress->delete();
            if ($default && ($next = $request->user()->shippingAddresses()->oldest()->first())) {
                $next->update(['is_default' => true]);
            }
        });

        return back()->with('success', 'Đã xóa địa chỉ.');
    }

    private function addressData(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:50'], 'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'], 'address' => ['required', 'string', 'max:1000'],
            'province_id' => ['nullable', 'integer'], 'province_name' => ['nullable', 'string', 'max:100'],
            'district_id' => ['nullable', 'integer'], 'district_name' => ['nullable', 'string', 'max:100'],
            'ward_code' => ['nullable', 'string', 'max:20'], 'ward_name' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }
}
