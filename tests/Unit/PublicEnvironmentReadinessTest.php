<?php

namespace Tests\Unit;

use App\Services\PublicEnvironmentReadiness;
use Tests\TestCase;

class PublicEnvironmentReadinessTest extends TestCase
{
    public function test_it_accepts_only_a_supported_application_key(): void
    {
        $readiness = app(PublicEnvironmentReadiness::class);
        config(['app.cipher' => 'AES-256-CBC', 'app.key' => '']);
        $this->assertFalse($readiness->appKeyIsValid());

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->assertTrue($readiness->appKeyIsValid());

        config(['app.key' => 'base64:not-valid-base64']);
        $this->assertFalse($readiness->appKeyIsValid());
    }

    public function test_it_rejects_placeholder_local_private_and_non_https_urls(): void
    {
        $readiness = app(PublicEnvironmentReadiness::class);

        foreach ([
            null,
            'not-a-url',
            'http://shop.example.vn/hook',
            'https://localhost/hook',
            'https://127.0.0.1/hook',
            'https://192.168.1.2/hook',
            'https://example.com/hook',
            'https://alerts.example.test/hook',
        ] as $url) {
            $this->assertFalse($readiness->publicHttpsUrl($url));
        }

        $this->assertTrue($readiness->publicHttpsUrl('https://alerts.vuabeach.vn/mail-failed'));
        $this->assertTrue($readiness->publicHttpsUrl('https://public@example.ingest.sentry.io/123'));
    }

    public function test_it_enforces_secret_length_in_bytes(): void
    {
        $readiness = app(PublicEnvironmentReadiness::class);

        $this->assertFalse($readiness->secretHasMinimumBytes(null, 32));
        $this->assertFalse($readiness->secretHasMinimumBytes(str_repeat('a', 31), 32));
        $this->assertTrue($readiness->secretHasMinimumBytes(str_repeat('a', 32), 32));
    }

    public function test_it_requires_public_callback_to_use_the_application_origin(): void
    {
        $readiness = app(PublicEnvironmentReadiness::class);

        $this->assertTrue($readiness->sameOriginPublicHttpsUrl(
            'https://shop.vuabeach.vn/thanh-toan/vnpay/ipn',
            'https://shop.vuabeach.vn',
        ));
        $this->assertFalse($readiness->sameOriginPublicHttpsUrl(
            'https://callback.vuabeach.vn/thanh-toan/vnpay/ipn',
            'https://shop.vuabeach.vn',
        ));
        $this->assertFalse($readiness->sameOriginPublicHttpsUrl(
            'https://example.com/thanh-toan/vnpay/ipn',
            'https://shop.vuabeach.vn',
        ));
    }

    public function test_it_requires_an_explicitly_guarded_staging_database_name(): void
    {
        $readiness = app(PublicEnvironmentReadiness::class);

        $this->assertTrue($readiness->stagingDatabaseIsGuarded('vuabeach_staging', 'vuabeach_staging'));
        $this->assertTrue($readiness->stagingDatabaseIsGuarded('shop-uat', 'shop-uat'));
        $this->assertFalse($readiness->stagingDatabaseIsGuarded('vuabeach', 'vuabeach'));
        $this->assertFalse($readiness->stagingDatabaseIsGuarded('vuabeach_staging', 'vuabeach'));
        $this->assertFalse($readiness->stagingDatabaseIsGuarded(null, null));
    }
}
