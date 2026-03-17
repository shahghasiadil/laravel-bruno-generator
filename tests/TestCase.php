<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ShahGhasiAdil\LaravelBrunoGenerator\BrunoGeneratorServiceProvider;

class TestCase extends Orchestra
{
    /**
     * The latest response instance.
     *
     * @var mixed
     */
    protected static $latestResponse;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get package providers.
     *
     * @param  Application  $app
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            BrunoGeneratorServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     */
    public function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup default cache to use array driver
        config()->set('cache.default', 'array');

        // Set default app URL
        config()->set('app.url', 'http://localhost:8000');
    }
}
