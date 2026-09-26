<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_two_factor_authentication_and_receives_recovery_codes(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('two-factor.setup'))->assertOk()->assertSee('Bật xác thực hai lớp');
        $secret = $admin->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('recovery_codes', fn (array $codes) => count($codes) === 8);

        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->assertCount(8, $admin->fresh()->two_factor_recovery_codes);
        $this->assertNotSame($secret, $admin->getRawOriginal('two_factor_secret'));
    }

    public function test_customer_cannot_access_admin_two_factor_setup(): void
    {
        $customer = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('two-factor.setup'))->assertForbidden();
    }

    public function test_production_admin_routes_enforce_setup_and_fresh_two_factor_challenge(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $unconfirmed = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($unconfirmed)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('two-factor.setup'));

        $confirmed = User::factory()->create([
            'is_admin' => true,
            'email_verified_at' => now(),
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->actingAs($confirmed)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('two-factor.challenge'));

        $this->withSession(['admin_2fa_verified_at' => now()->timestamp])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('two-factor.challenge'));

        $this->post(route('two-factor.verify'), [
            '_token' => csrf_token(),
            'code' => app(Google2FA::class)->getCurrentOtp($confirmed->two_factor_secret),
        ])->assertRedirect();
        $this->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_reenrollment_invalidates_previously_verified_session(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        $admin = User::factory()->create([
            'is_admin' => true, 'email_verified_at' => now(),
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
        ]);
        $this->actingAs($admin)->get(route('two-factor.challenge'))->assertOk();
        $this->post(route('two-factor.verify'), [
            '_token' => csrf_token(),
            'code' => app(Google2FA::class)->getCurrentOtp($admin->two_factor_secret),
        ])->assertRedirect();
        $this->get(route('admin.dashboard'))->assertOk();
        $admin->forceFill(['two_factor_secret' => app(Google2FA::class)->generateSecretKey()])->save();
        $this->actingAs($admin->fresh())->get(route('admin.dashboard'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_confirmation_cannot_replace_existing_recovery_codes(): void
    {
        $codes = [Hash::make('RECOVERY01')];
        $admin = User::factory()->create([
            'is_admin' => true, 'email_verified_at' => now(),
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $codes,
        ]);
        $this->actingAs($admin)->post(route('two-factor.confirm'), [
            'code' => app(Google2FA::class)->getCurrentOtp($admin->two_factor_secret),
        ])->assertSessionHasErrors('code');
        $this->assertSame($codes, $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_recovery_code_is_consumed_once(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true, 'email_verified_at' => now(),
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('RECOVERY01')],
        ]);
        $this->actingAs($admin)->post(route('two-factor.verify'), ['code' => 'RECOVERY01'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame([], $admin->fresh()->two_factor_recovery_codes);
        $this->post(route('two-factor.verify'), ['code' => 'RECOVERY01'])->assertSessionHasErrors('code');
    }
}
