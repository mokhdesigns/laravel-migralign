<?php

namespace MigrAlign\Tests;

use MigrAlign\MigrAlignServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MigrAlignServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('migralign.migrations_path', __DIR__.'/fixtures/migrations');
        $app['config']->set('migralign.ignored_tables', ['migrations']);
        $app['config']->set('migralign.auto_apply_safe', true);
    }
}
