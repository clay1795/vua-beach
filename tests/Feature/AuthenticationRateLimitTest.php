<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_limited_per_account_and_ip(): void
    {
        User::factory()->create(['username' => 'rate-user']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'username' => 'rate-user',
                'password' => 'wrong-password-'.$attempt,
            ])->assertSessionHasErrors('username');
        }

        $this->post(route('login.store'), [
            'username' => 'rate-user',
            'password' => 'wrong-password-final',
        ])->assertTooManyRequests();
    }

    public function test_registration_is_limited_even_when_validation_fails(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('register.store'), [
                'email' => 'same@example.com',
            ])->assertSessionHasErrors(['name', 'username', 'password']);
        }

        $this->post(route('register.store'), [
            'email' => 'same@example.com',
        ])->assertTooManyRequests();
    }

    public function test_verification_resend_enforces_the_same_sixty_second_wait_as_the_ui(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);

        $this->post(route('verification.send'))->assertTooManyRequests();
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
    }
}
