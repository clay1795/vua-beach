<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_a_verification_link(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Khách mới',
            'username' => 'khachmoi',
            'email' => 'khachmoi@example.com',
            'phone' => '0900000000',
            'password' => 'StrongPass!123',
            'password_confirmation' => 'StrongPass!123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'khachmoi@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTimes(VerifyEmailNotification::class, 1);
    }

    public function test_local_registration_remembers_the_current_origin_for_the_queued_email(): void
    {
        Notification::fake();
        $this->app->detectEnvironment(fn () => 'local');
        $this->app->instance('request', Request::create('https://fresh-name.ngrok-free.dev/dang-ky', 'POST'));

        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmailNotification::class,
            fn (VerifyEmailNotification $notification): bool => $notification->origin === 'https://fresh-name.ngrok-free.dev');
    }

    public function test_local_password_reset_remembers_the_current_origin_for_the_queued_email(): void
    {
        Notification::fake();
        $this->app->detectEnvironment(fn () => 'local');
        $this->app->instance('request', Request::create('http://localhost:8000/quen-mat-khau', 'POST'));

        $user = User::factory()->create();
        $user->sendPasswordResetNotification('reset-token');

        Notification::assertSentTo($user, ResetPasswordNotification::class,
            fn (ResetPasswordNotification $notification): bool => $notification->origin === 'http://localhost:8000'
                && str_starts_with($notification->toMail($user)->actionUrl, 'http://localhost:8000/dat-lai-mat-khau/reset-token'));
    }

    public function test_unverified_user_cannot_add_to_cart_or_view_order_history(): void
    {
        $user = User::factory()->unverified()->create();
        $category = Category::create(['name' => 'Đồ bơi', 'slug' => 'do-boi']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Đồ bơi kiểm tra',
            'slug' => 'do-boi-kiem-tra',
            'price' => 200000,
            'description' => 'Sản phẩm kiểm tra',
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Xanh',
            'size' => 'M',
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertRedirect(route('verification.notice'));
        $this->assertNull(session('cart.'.$variant->id));

        $this->get(route('orders.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_add_to_cart_and_view_order_history(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Bikini', 'slug' => 'bikini']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Bikini xác thực',
            'slug' => 'bikini-xac-thuc',
            'price' => 250000,
            'description' => 'Sản phẩm kiểm tra',
            'status' => 'active',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Đen',
            'size' => 'L',
            'stock' => 3,
        ]);

        $this->actingAs($user)
            ->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertRedirect(route('cart.index'));
        $this->assertSame(1, session('cart.'.$variant->id.'.quantity'));
        $this->get(route('orders.index'))->assertOk();
    }

    public function test_changing_email_requires_verification_again(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => 'new@example.com',
            'phone' => '0900000000',
            'address' => 'Hà Nội',
            'current_password' => 'password',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('verification.notice'));

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_profile_exposes_resend_action_and_server_cooldown_timestamp(): void
    {
        $user = User::factory()->unverified()->create();
        $sentAt = now()->timestamp;

        $this->actingAs($user)
            ->withSession(['verification_sent_at' => $sentAt])
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Email chưa xác thực')
            ->assertSee('data-verification-resend', false)
            ->assertSee('data-sent-at="'.$sentAt.'"', false)
            ->assertSee('data-resend-label', false);
    }

    public function test_changing_email_or_password_requires_the_current_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'secure@example.com']);
        $base = [
            'name' => $user->name,
            'username' => $user->username,
            'phone' => $user->phone,
            'address' => $user->address,
        ];

        $this->actingAs($user)->patch(route('profile.update'), $base + [
            'email' => 'attacker@example.com',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasErrors([
            'current_password' => 'Vui lòng nhập mật khẩu hiện tại.',
        ]);
        $this->assertSame('secure@example.com', $user->fresh()->email);

        $this->patch(route('profile.update'), $base + [
            'email' => $user->email,
            'current_password' => 'wrong-password',
            'password' => 'ChangedPass!123',
            'password_confirmation' => 'ChangedPass!123',
        ])->assertSessionHasErrors([
            'current_password' => 'Mật khẩu hiện tại không chính xác.',
        ]);
        $this->assertTrue(password_verify('password', $user->fresh()->password));
        Notification::assertNothingSent();
    }

    public function test_current_password_allows_a_secure_password_change(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'current_password' => 'password',
            'password' => 'ChangedPass!123',
            'password_confirmation' => 'ChangedPass!123',
        ])->assertRedirect();

        $this->assertTrue(password_verify('ChangedPass!123', $user->fresh()->password));
        $this->get(route('profile.edit'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_change_immediately_removes_other_database_sessions(): void
    {
        config()->set('session.driver', 'database');
        $user = User::factory()->create();
        foreach (['other-device-one', 'other-device-two'] as $sessionId) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test browser',
                'payload' => '{}',
                'last_activity' => now()->timestamp,
            ]);
        }

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'current_password' => 'password',
            'password' => 'ChangedPass!123',
            'password_confirmation' => 'ChangedPass!123',
        ])->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-one']);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-two']);
        $this->assertAuthenticatedAs($user);
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_a_session_bound_to_an_old_password_is_logged_out(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $user->update(['password' => Hash::make('ChangedElsewhere!123')]);

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $oldHash])
            ->get(route('profile.edit'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
