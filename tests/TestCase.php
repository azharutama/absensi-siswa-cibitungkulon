<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the application and reject unsafe database targets before
     * RefreshDatabase is allowed to run migrate:fresh.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || ! str_ends_with(strtolower($database), '_test')) {
            throw new LogicException(
                "Test dibatalkan: database [{$database}] bukan database testing yang berakhiran _test."
            );
        }

        return $app;
    }
}
