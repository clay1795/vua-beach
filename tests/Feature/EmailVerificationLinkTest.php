<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_relative_verification_signature_is_valid_on_localhost_and_tunnel_hosts(): void
    {
        $user = User::factory()->unverified()->create();
        $path = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ], absolute: false);

        $this->assertTrue(URL::hasValidSignature(Request::create('http://127.0.0.1:8000'.$path), false));
        $this->assertTrue(URL::hasValidSignature(Request::create('https://example.ngrok-free.dev'.$path), false));
    }
}
