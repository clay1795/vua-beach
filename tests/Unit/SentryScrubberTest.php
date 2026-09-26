<?php

namespace Tests\Unit;

use App\Support\SentryScrubber;
use Sentry\Event;
use Tests\TestCase;

class SentryScrubberTest extends TestCase
{
    public function test_it_removes_request_bodies_and_redacts_secrets_from_request_metadata(): void
    {
        $event = Event::createEvent()->setRequest([
            'url' => 'https://shop.test/dat-lai-mat-khau/reset-token?secret=ghn-secret&vnp_SecureHash=signed-value',
            'query_string' => 'secret=ghn-secret&signature=momo-signature&safe=value',
            'data' => ['password' => 'NeverLeaveHost!123'],
            'cookies' => ['session' => 'private-cookie'],
            'headers' => [
                'Authorization' => ['Bearer private-token'],
                'X-GHN-Webhook-Secret' => ['ghn-secret'],
                'Accept' => ['application/json'],
            ],
        ]);

        $request = SentryScrubber::scrub($event)->getRequest();
        $serialized = json_encode($request);

        $this->assertArrayNotHasKey('data', $request);
        $this->assertArrayNotHasKey('cookies', $request);
        foreach (['NeverLeaveHost!123', 'reset-token', 'ghn-secret', 'signed-value', 'momo-signature', 'private-token'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $this->assertStringContainsString('safe=value', $request['query_string']);
        $this->assertSame(['application/json'], $request['headers']['Accept']);
    }
}
