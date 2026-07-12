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
        $driver = (string) $app['config']->get("database.connections.{$connection}.driver");
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if (
            ! $app->environment('testing')
            || $connection !== 'mysql'
            || $driver !== 'mysql'
            || ! str_ends_with(strtolower($database), '_test')
        ) {
            throw new LogicException(
                "Test dibatalkan: koneksi [{$connection}/{$driver}] ke database [{$database}] "
                .'harus menggunakan MySQL dan nama database berakhiran _test.'
            );
        }

        return $app;
    }
}
