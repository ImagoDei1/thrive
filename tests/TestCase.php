<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but the testing environment. A cached
     * config (php artisan optimize) makes Laravel ignore phpunit.xml's env
     * overrides, so tests would otherwise hit the live database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if ($app->configurationIsCached() || $app->routesAreCached() || $app['config']->get('app.env') !== 'testing') {
            throw new RuntimeException(
                'Tests must run with APP_ENV=testing and without cached config/routes (see phpunit.xml).'
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must never reach real services (Bunny, PayChangu, ...). Any
        // HTTP call that is not covered by Http::fake() fails the test.
        Http::preventStrayRequests();
    }
}
