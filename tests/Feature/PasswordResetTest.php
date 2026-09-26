<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_links_to_the_password_recovery_flow(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'))
            ->assertSee('Quên mật khẩu?');

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Vì bảo mật, thông báo luôn giống nhau');

        $this->get(route('password.reset', ['token' => 'temporary-secret', 'email' => 'customer@example.test']))
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertSee('customer@example.test')
            ->assertSee('temporary-secret');
    }

    public function test_reset_link_response_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'customer@example.test']);
        $genericMessage = 'Nếu email thuộc một tài khoản, liên kết đặt lại mật khẩu sẽ được gửi trong ít phút.';

        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', $genericMessage);
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', $genericMessage);

        Notification::assertSentTo($user, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification) => $notification->afterCommit === true && $notification->tries === 5);
        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    }

    public function test_password_reset_changes_password_removes_token_and_revokes_sessions(): void
    {
        config()->set('session.driver', 'database');
        $user = User::factory()->create(['email' => 'reset@example.test']);
        $token = Password::broker()->createToken($user);
        foreach (['reset-session-one', 'reset-session-two'] as $sessionId) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Old browser',
                'payload' => '{}',
                'last_activity' => now()->timestamp,
            ]);
        }

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'RecoveredPass!123',
            'password_confirmation' => 'RecoveredPass!123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Mật khẩu đã được đặt lại. Bạn có thể đăng nhập ngay.');

        $this->assertTrue(Hash::check('RecoveredPass!123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'RecoveredPass!123'])
            ->assertRedirect(route('home'));
    }

    public function test_invalid_or_expired_reset_token_is_rejected_in_vietnamese(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'RecoveredPass!123',
            'password_confirmation' => 'RecoveredPass!123',
        ])->assertSessionHasErrors([
            'email' => 'Mã đặt lại mật khẩu không hợp lệ.',
        ]);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_link_endpoint_is_rate_limited(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('password.email'), ['email' => 'limited@example.test'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'limited@example.test'])->assertTooManyRequests();
        Notification::assertNothingSent();
    }
}
