<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not inherit a deployment server's opt-in to Sandbox.
        // Individual tests that exercise the opt-in enable it explicitly.
        config()->set('services.payment_sandbox_mode', false);
    }
}
